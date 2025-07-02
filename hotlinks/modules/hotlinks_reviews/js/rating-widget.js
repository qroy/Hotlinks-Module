/**
 * @file
 * Interactive rating widget for Hotlinks Reviews.
 */

(function ($, Drupal) {
  'use strict';

  /**
   * Rating widget behavior.
   */
  Drupal.behaviors.hotlinksRatingWidget = {
    attach: function (context, settings) {
      $('.hotlinks-rating-widget .rating-star', context).once('rating-widget').each(function () {
        var $widget = $(this).closest('.hotlinks-rating-widget');
        var $stars = $widget.find('.rating-star');
        var $hiddenInput = $widget.find('input[type="hidden"], input[type="number"]');
        var $label = $widget.find('.rating-label');
        
        var ratingLabels = [
          '', // 0 - no rating
          'Poor - Not useful',
          'Fair - Somewhat useful', 
          'Good - Useful',
          'Very Good - Very useful',
          'Excellent - Extremely useful'
        ];

        // Star Trek themed labels (if enabled)
        var trekLabels = [
          '', // 0 - no rating
          'Illogical - Not recommended',
          'Acceptable - Proceed with caution',
          'Logical - Recommended',
          'Fascinating - Highly recommended',
          'Live Long and Prosper - Essential!'
        ];

        // Check if Star Trek labels are enabled
        var useStarTrekLabels = $widget.data('star-trek-labels') || false;
        var labels = useStarTrekLabels ? trekLabels : ratingLabels;

        // Initialize current rating
        var currentRating = parseInt($hiddenInput.val()) || 0;
        updateStars(currentRating);

        // Star hover effects
        $stars.on('mouseenter', function () {
          var hoverRating = $(this).data('rating');
          updateStars(hoverRating, true);
          updateLabel(hoverRating);
        });

        // Reset on mouse leave
        $widget.on('mouseleave', function () {
          updateStars(currentRating);
          updateLabel(currentRating);
        });

        // Click to rate
        $stars.on('click', function (e) {
          e.preventDefault();
          var newRating = $(this).data('rating');
          
          // Allow clicking same star to clear rating
          if (newRating === currentRating) {
            newRating = 0;
          }
          
          currentRating = newRating;
          $hiddenInput.val(newRating).trigger('change');
          updateStars(newRating);
          updateLabel(newRating);
          
          // Add animation
          $(this).addClass('just-rated');
          setTimeout(function () {
            $('.rating-star.just-rated').removeClass('just-rated');
          }, 600);
          
          // Trigger custom event
          $widget.trigger('ratingChanged', [newRating]);
        });

        // Keyboard support
        $stars.on('keydown', function (e) {
          var $star = $(this);
          var rating = $star.data('rating');
          
          switch (e.which) {
            case 13: // Enter
            case 32: // Space
              e.preventDefault();
              $star.click();
              break;
            case 37: // Left arrow
              e.preventDefault();
              if (rating > 1) {
                $stars.filter('[data-rating="' + (rating - 1) + '"]').focus();
              }
              break;
            case 39: // Right arrow
              e.preventDefault();
              if (rating < 5) {
                $stars.filter('[data-rating="' + (rating + 1) + '"]').focus();
              }
              break;
          }
        });

        // Make stars focusable
        $stars.attr('tabindex', '0').attr('role', 'button');

        function updateStars(rating, isHover) {
          $stars.each(function () {
            var starRating = $(this).data('rating');
            $(this).removeClass('selected hover empty');
            
            if (starRating <= rating) {
              $(this).addClass(isHover ? 'hover' : 'selected');
            } else {
              $(this).addClass('empty');
            }
          });
          
          // Update ARIA attributes
          $widget.attr('aria-label', 'Rating: ' + rating + ' out of 5 stars');
        }

        function updateLabel(rating) {
          if ($label.length) {
            var labelText = rating > 0 ? labels[rating] : 'Click to rate';
            $label.text(labelText);
          }
        }

        // Initialize label
        updateLabel(currentRating);
        
        // Set initial ARIA attributes
        $widget.attr('role', 'radiogroup').attr('aria-label', 'Rate this hotlink');
      });
    }
  };

  /**
   * AJAX rating submission.
   */
  Drupal.behaviors.hotlinksAjaxRating = {
    attach: function (context, settings) {
      $('.hotlinks-rating-widget', context).once('ajax-rating').on('ratingChanged', function (e, rating) {
        var $widget = $(this);
        var nodeId = $widget.data('node-id');
        
        if (!nodeId) return;
        
        // Show loading state
        $widget.addClass('loading').attr('aria-busy', 'true');
        
        $.ajax({
          url: '/hotlinks/ajax/rate/' + nodeId,
          method: 'POST',
          data: {
            rating: rating,
            _token: $widget.data('csrf-token')
          },
          success: function (response) {
            if (response.status === 'success') {
              // Update average rating display if present
              var $avgDisplay = $('.field--name-field-hotlink-avg-rating');
              if ($avgDisplay.length && response.newAverageHtml) {
                $avgDisplay.find('.hotlinks-rating-stars').replaceWith(response.newAverageHtml);
              }
              
              // Show success message
              Drupal.announce('Rating saved successfully');
              
              // Flash the widget briefly
              $widget.addClass('success-flash');
              setTimeout(function () {
                $widget.removeClass('success-flash');
              }, 1000);
            } else {
              Drupal.announce('Error saving rating: ' + response.message);
            }
          },
          error: function () {
            Drupal.announce('Error saving rating. Please try again.');
          },
          complete: function () {
            $widget.removeClass('loading').attr('aria-busy', 'false');
          }
        });
      });
    }
  };

  /**
   * Animated star display for average ratings.
   */
  Drupal.behaviors.hotlinksAnimatedStars = {
    attach: function (context, settings) {
      $('.hotlinks-rating-stars', context).once('animated-stars').each(function () {
        var $container = $(this);
        var rating = parseFloat($container.data('rating')) || 0;
        var maxRating = parseInt($container.data('max-rating')) || 5;
        
        // Create stars if they don't exist
        if ($container.find('.hotlinks-rating-star').length === 0) {
          for (var i = 1; i <= maxRating; i++) {
            var $star = $('<span class="hotlinks-rating-star">★</span>');
            
            if (i <= Math.floor(rating)) {
              $star.addClass('filled');
            } else if (i <= rating) {
              $star.addClass('half');
            } else {
              $star.addClass('empty');
            }
            
            $container.append($star);
          }
          
          // Add count if provided
          var count = parseInt($container.data('count'));
          if (count > 0) {
            $container.append('<span class="hotlinks-rating-count">' + count + '</span>');
          }
        }
        
        // Animate stars on scroll into view
        if ('IntersectionObserver' in window) {
          var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
              if (entry.isIntersecting) {
                animateStars($(entry.target));
                observer.unobserve(entry.target);
              }
            });
          }, { threshold: 0.5 });
          
          observer.observe($container[0]);
        }
      });
      
      function animateStars($container) {
        var $stars = $container.find('.hotlinks-rating-star');
        $stars.each(function (index) {
          var $star = $(this);
          setTimeout(function () {
            $star.addClass('animate-in');
          }, index * 100);
        });
      }
    }
  };

  /**
   * Review summary interactive elements.
   */
  Drupal.behaviors.hotlinksReviewSummary = {
    attach: function (context, settings) {
      $('.hotlinks-review-summary .rating-breakdown', context).once('interactive-breakdown').each(function () {
        var $breakdown = $(this);
        
        // Animate bars on load
        $breakdown.find('.bar-fill').each(function () {
          var $fill = $(this);
          var width = $fill.data('width') || 0;
          
          // Start at 0 width
          $fill.css('width', '0%');
          
          // Animate to target width
          setTimeout(function () {
            $fill.css('width', width + '%');
          }, 200);
        });
      });
    }
  };

  /**
   * Success flash animation CSS class.
   */
  $(document).ready(function () {
    $('<style>')
      .prop('type', 'text/css')
      .html(`
        .hotlinks-rating-widget.success-flash {
          background: rgba(0, 255, 0, 0.1);
          border-radius: 4px;
          transition: background 0.3s ease;
        }
        
        .hotlinks-rating-star.animate-in {
          animation: starPop 0.3s ease-out;
        }
        
        @keyframes starPop {
          0% { transform: scale(0.5); opacity: 0; }
          50% { transform: scale(1.2); }
          100% { transform: scale(1); opacity: 1; }
        }
      `)
      .appendTo('head');
  });

})(jQuery, Drupal);