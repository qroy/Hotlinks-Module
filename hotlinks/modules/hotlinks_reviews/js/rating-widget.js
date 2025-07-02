/**
 * @file
 * Interactive rating widget for Hotlinks Reviews with enhanced security.
 */

(function ($, Drupal, once) {
  'use strict';

  // Security token cache
  let tokenCache = {};

  /**
   * Get CSRF token for a specific action and node.
   */
  async function getSecurityToken(action, nodeId) {
    const cacheKey = `${action}_${nodeId}`;
    
    // Check cache first (tokens expire after 1 hour)
    if (tokenCache[cacheKey] && tokenCache[cacheKey].expires > Date.now()) {
      return tokenCache[cacheKey].token;
    }
    
    try {
      const response = await fetch(`/hotlinks/ajax/token?action=${encodeURIComponent(action)}&node_id=${encodeURIComponent(nodeId)}`, {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
        },
        credentials: 'same-origin'
      });
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const data = await response.json();
      
      // Cache the token
      tokenCache[cacheKey] = {
        token: data.token,
        expires: Date.now() + (55 * 60 * 1000) // 55 minutes (5 min buffer)
      };
      
      return data.token;
    } catch (error) {
      console.error('Failed to get security token:', error);
      Drupal.announce('Security error. Please refresh the page and try again.');
      return null;
    }
  }

  /**
   * Sanitize text input to prevent XSS.
   */
  function sanitizeInput(input) {
    if (typeof input !== 'string') {
      return '';
    }
    
    // Create a temporary element to leverage browser's built-in escaping
    const temp = document.createElement('div');
    temp.textContent = input;
    return temp.innerHTML;
  }

  /**
   * Validate rating value.
   */
  function validateRating(rating) {
    const numRating = parseInt(rating, 10);
    return !isNaN(numRating) && numRating >= 1 && numRating <= 5;
  }

  /**
   * Rating widget behavior.
   */
  Drupal.behaviors.hotlinksRatingWidget = {
    attach: function (context, settings) {
      once('rating-widget', '.hotlinks-rating-widget .rating-star', context).forEach(function (element) {
        var $star = $(element);
        var $widget = $star.closest('.hotlinks-rating-widget');
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
          
          // Validate rating
          if (!validateRating(newRating)) {
            Drupal.announce('Invalid rating value.');
            return;
          }
          
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

        // Keyboard support with security considerations
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
            case 27: // Escape - clear focus for security
              e.preventDefault();
              $(this).blur();
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
   * AJAX rating submission with enhanced security.
   */
  Drupal.behaviors.hotlinksAjaxRating = {
    attach: function (context, settings) {
      once('ajax-rating', '.hotlinks-rating-widget', context).forEach(function (element) {
        var $widget = $(element);
        
        $widget.on('ratingChanged', async function (e, rating) {
          var nodeId = $widget.data('node-id');
          
          if (!nodeId) {
            console.error('No node ID found for rating widget');
            return;
          }
          
          // Validate rating on client side
          if (!validateRating(rating) && rating !== 0) {
            Drupal.announce('Invalid rating value.');
            return;
          }
          
          // Show loading state
          $widget.addClass('loading').attr('aria-busy', 'true');
          
          try {
            // Get security token
            const token = await getSecurityToken('rate', nodeId);
            if (!token) {
              throw new Error('Failed to obtain security token');
            }
            
            // Prepare form data
            const formData = new FormData();
            formData.append('rating', rating.toString());
            formData.append('token', token);
            
            const response = await fetch(`/hotlinks/ajax/rate/${nodeId}`, {
              method: 'POST',
              body: formData,
              credentials: 'same-origin',
              headers: {
                'X-Requested-With': 'XMLHttpRequest'
              }
            });
            
            if (!response.ok) {
              throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.status === 'success') {
              // Update average rating display if present
              const $avgDisplay = $('.field--name-field-hotlink-avg-rating');
              if ($avgDisplay.length && data.data && data.data.newAverage) {
                // Safely update the display
                $avgDisplay.find('.rating-number').text(data.data.newAverage.toFixed(1));
                
                // Update review count if present
                if (data.data.reviewCount) {
                  $avgDisplay.find('.rating-count').text(data.data.reviewCount);
                }
              }
              
              // Show success message
              Drupal.announce(data.message || 'Rating saved successfully');
              
              // Flash the widget briefly
              $widget.addClass('success-flash');
              setTimeout(function () {
                $widget.removeClass('success-flash');
              }, 1000);
              
            } else {
              throw new Error(data.message || 'Unknown error occurred');
            }
            
          } catch (error) {
            console.error('Rating submission error:', error);
            
            // Show user-friendly error message
            let errorMessage = 'Error saving rating. Please try again.';
            if (error.message && error.message.includes('token')) {
              errorMessage = 'Security error. Please refresh the page and try again.';
            }
            
            Drupal.announce(errorMessage);
            
            // Reset the widget to previous state if needed
            // (Implementation depends on how you want to handle failures)
            
          } finally {
            $widget.removeClass('loading').attr('aria-busy', 'false');
          }
        });
      });
    }
  };

  /**
   * AJAX review submission with enhanced security.
   */
  Drupal.behaviors.hotlinksAjaxReviews = {
    attach: function (context, settings) {
      once('ajax-review-form', '.hotlinks-review-form', context).forEach(function (element) {
        var $form = $(element);
        
        $form.on('submit', async function (e) {
          e.preventDefault();
          
          var nodeId = $form.data('node-id');
          var $reviewField = $form.find('[name="review"]');
          var $ratingField = $form.find('[name="rating"]');
          var $submitButton = $form.find('[type="submit"]');
          
          if (!nodeId) {
            console.error('No node ID found for review form');
            return;
          }
          
          // Get and validate form data
          var reviewText = sanitizeInput($reviewField.val().trim());
          var rating = $ratingField.val();
          
          // Client-side validation
          if (reviewText.length < 10) {
            Drupal.announce('Review text must be at least 10 characters long.');
            $reviewField.focus();
            return;
          }
          
          if (reviewText.length > 2000) {
            Drupal.announce('Review text is too long. Maximum 2000 characters.');
            $reviewField.focus();
            return;
          }
          
          if (rating && !validateRating(rating)) {
            Drupal.announce('Please select a valid rating (1-5 stars).');
            return;
          }
          
          // Show loading state
          $submitButton.prop('disabled', true).text('Submitting...');
          $form.addClass('loading');
          
          try {
            // Get security token
            const token = await getSecurityToken('review', nodeId);
            if (!token) {
              throw new Error('Failed to obtain security token');
            }
            
            // Prepare form data
            const formData = new FormData();
            formData.append('review', reviewText);
            if (rating) {
              formData.append('rating', rating.toString());
            }
            formData.append('token', token);
            
            const response = await fetch(`/hotlinks/ajax/review/${nodeId}`, {
              method: 'POST',
              body: formData,
              credentials: 'same-origin',
              headers: {
                'X-Requested-With': 'XMLHttpRequest'
              }
            });
            
            if (!response.ok) {
              throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.status === 'success') {
              // Show success message
              Drupal.announce(data.message || 'Review submitted successfully');
              
              // Clear form
              $reviewField.val('');
              $ratingField.val('');
              
              // Show moderation notice if needed
              if (data.data && data.data.needsModeration) {
                $form.after('<div class="messages messages--warning" role="alert">' + 
                  'Your review has been submitted and is pending moderation.' + 
                  '</div>');
              }
              
            } else {
              throw new Error(data.message || 'Unknown error occurred');
            }
            
          } catch (error) {
            console.error('Review submission error:', error);
            
            // Show user-friendly error message
            let errorMessage = 'Error submitting review. Please try again.';
            if (error.message && error.message.includes('token')) {
              errorMessage = 'Security error. Please refresh the page and try again.';
            } else if (error.message && error.message.includes('rate limit')) {
              errorMessage = 'Too many submissions. Please wait before submitting another review.';
            }
            
            Drupal.announce(errorMessage);
            
          } finally {
            // Reset loading state
            $submitButton.prop('disabled', false).text('Submit Review');
            $form.removeClass('loading');
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
      once('animated-stars', '.hotlinks-rating-stars', context).forEach(function (element) {
        var $container = $(element);
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
      once('interactive-breakdown', '.hotlinks-review-summary .rating-breakdown', context).forEach(function (element) {
        var $breakdown = $(element);
        
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
   * Security enhancement: Clear sensitive data on page unload.
   */
  $(window).on('beforeunload', function() {
    // Clear token cache to prevent memory leaks
    tokenCache = {};
    
    // Clear any sensitive form data
    $('.hotlinks-review-form').each(function() {
      $(this).find('input, textarea').val('');
    });
  });

  /**
   * Security enhancement: Rate limiting feedback.
   */
  Drupal.behaviors.hotlinksRateLimitFeedback = {
    attach: function (context, settings) {
      // Track submission attempts for client-side rate limiting feedback
      var submissionCount = 0;
      var submissionTimes = [];
      var maxSubmissions = 5;
      var timeWindow = 5 * 60 * 1000; // 5 minutes
      
      once('rate-limit-tracking', '.hotlinks-rating-widget, .hotlinks-review-form', context).forEach(function (element) {
        var $element = $(element);
        
        $element.on('ratingChanged submit', function() {
          var currentTime = Date.now();
          
          // Clean old submissions
          submissionTimes = submissionTimes.filter(function(time) {
            return (currentTime - time) < timeWindow;
          });
          
          // Add current submission
          submissionTimes.push(currentTime);
          
          // Check if approaching rate limit
          if (submissionTimes.length >= maxSubmissions - 1) {
            Drupal.announce('You are approaching the submission limit. Please wait before submitting more ratings or reviews.');
          }
        });
      });
    }
  };

})(jQuery, Drupal, once);