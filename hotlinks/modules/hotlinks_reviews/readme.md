# Hotlinks Reviews

A rating and review submodule for the Hotlinks module, featuring Star Trek themed styling and comprehensive review functionality.

## Description

This submodule adds rating and review capabilities to your Hotlinks module, allowing users to rate links with 1-5 stars and write detailed reviews. It integrates seamlessly with the main Hotlinks interface and maintains the Star Trek theme.

## Location

This submodule is located within the main Hotlinks module:
```
modules/custom/hotlinks/
├── hotlinks.info.yml
├── hotlinks.module
├── [other main module files]
└── modules/
    ├── hotlinks_startrek/          # Star Trek Categories submodule
    └── hotlinks_reviews/           # Reviews submodule (this module)
        ├── hotlinks_reviews.info.yml
        ├── hotlinks_reviews.install
        ├── hotlinks_reviews.module
        ├── hotlinks_reviews.permissions.yml
        ├── hotlinks_reviews.routing.yml
        ├── hotlinks_reviews.libraries.yml
        ├── src/
        │   ├── Controller/
        │   │   └── HotlinksReviewsAjaxController.php
        │   └── Plugin/Field/
        │       ├── FieldWidget/
        │       │   └── HotlinksRatingWidget.php
        │       └── FieldFormatter/
        │           └── HotlinksRatingFormatters.php
        ├── templates/
        │   ├── hotlinks-rating-stars.html.twig
        │   ├── hotlinks-rating-widget.html.twig
        │   └── hotlinks-review-summary.html.twig
        ├── css/
        │   └── hotlinks-reviews.css
        ├── js/
        │   └── rating-widget.js
        └── README.md (this file)
```

## Features

- **5-Star Rating System** with interactive widgets
- **Text Reviews** with optional moderation
- **Star Trek Themed Styling** throughout the interface
- **Starfleet Approval Badges** for highly rated links
- **Review Statistics** and analytics
- **AJAX Rating Submission** for smooth user experience
- **Integrated Settings** in the main Hotlinks configuration
- **Keyboard Accessibility** for rating widgets
- **Responsive Design** for mobile devices

## Installation

1. **Ensure main Hotlinks module is installed and enabled**
2. Go to Extend (`/admin/modules`)
3. Find "Hotlinks Reviews" under the Hotlinks package
4. Enable the module
5. Clear cache: `drush cr`

Or via Drush:
```bash
drush en hotlinks_reviews
drush cr
```

### If Enabling Both Modules at Once
If you enable both the main Hotlinks module and Reviews submodule at the same time, the review fields might not get created. If this happens:

```bash
# Run the update hook to create missing fields
drush updb
```

Or disable and re-enable the Reviews submodule:
```bash
drush pmu hotlinks_reviews -y
drush en hotlinks_reviews -y
```

## Quick Setup

### 1. Configure Permissions
Go to **People > Permissions** (`/admin/people/permissions`):

**Recommended for Authenticated Users:**
- ✅ Rate hotlinks
- ✅ Review hotlinks  
- ✅ View hotlink ratings

**For Moderators/Administrators:**
- ✅ Moderate hotlink reviews
- ✅ Administer hotlink reviews

### 2. Configure Settings
Go to **Configuration > Content authoring > Hotlinks** (`/admin/config/content/hotlinks`)

You'll now see a "Reviews & Ratings" section with options for:
- Anonymous review permissions
- Review moderation settings
- Star Trek theme elements
- Display preferences

### 3. Test It Out!
1. Go to any hotlink page
2. Click the stars to rate (1-5)
3. Optionally add a review
4. See your rating appear in the hotlinks index!

## Usage

### For End Users

**Rating a Hotlink:**
1. Visit any hotlink detail page
2. Click on the stars (1-5) to rate
3. Rating saves automatically via AJAX
4. Your rating appears immediately

**Writing a Review:**
1. Rate the hotlink first
2. Add text in the review field
3. Submit the form
4. Review may require approval (if moderation enabled)

**Keyboard Navigation:**
- Use Tab to navigate to rating stars
- Use arrow keys to move between stars
- Press Enter or Space to select a rating

### For Administrators

**Review Moderation:**
- Configure moderation in main Hotlinks settings
- Reviews can require approval before display
- Trusted users can be auto-approved

**Star Trek Theme:**
- Enable Starfleet approval badges
- Use Trek-themed rating labels
- Customize the blue color scheme

**Statistics:**
- View review statistics in settings page
- Monitor average ratings across all hotlinks
- Track review activity

## Star Trek Theme Elements

When enabled, the Star Trek theme includes:

- **Starfleet Approval Badges**: 4+ star links get "🖖 Starfleet Command Approved" badges
- **Trek Color Scheme**: Star Trek blue (#0066CC) throughout the interface
- **Themed Rating Labels**: 
  - 1 star: "Illogical - Not recommended"
  - 2 stars: "Acceptable - Proceed with caution"  
  - 3 stars: "Logical - Recommended"
  - 4 stars: "Fascinating - Highly recommended"
  - 5 stars: "Live Long and Prosper - Essential!"

## Technical Details

### Fields Added
- `field_hotlink_rating`: Individual user ratings (1-5)
- `field_hotlink_review`: Text reviews  
- `field_hotlink_avg_rating`: Calculated average rating
- `field_hotlink_review_count`: Total review count

### Display Modes Updated
- **Default view**: Full rating display with review summary
- **Hotlinks index**: Compact star ratings with counts

### AJAX Endpoints
- `/hotlinks/ajax/rate/{node}`: Submit ratings
- `/hotlinks/ajax/review/{node}`: Submit reviews

### Data Storage
- Uses Drupal state system for demonstration
- Production should migrate to dedicated database tables
- All data cleared on module uninstall

## Customization

### CSS Classes
Style these classes in your theme:
- `.hotlinks-rating-stars`: Star containers
- `.hotlinks-rating-widget`: Interactive rating forms  
- `.hotlinks-review-summary`: Review summary blocks
- `.starfleet-approval`: Star Trek approval badges

### Template Overrides
Override these in your theme:
- `hotlinks-rating-stars.html.twig`
- `hotlinks-rating-widget.html.twig` 
- `hotlinks-review-summary.html.twig`

### JavaScript Events
Listen for custom events:
- `ratingChanged`: When user changes rating
- `reviewSubmitted`: When review is submitted

## Troubleshooting

### Fields Not Created
```bash
drush updb  # Run update hooks
# or
drush pmu hotlinks_reviews -y && drush en hotlinks_reviews -y
```

### Settings Not Appearing
- Ensure submodule is enabled: `drush en hotlinks_reviews`
- Clear cache: `drush cr`
- Check main Hotlinks module is working

### Ratings Not Saving
- Check user has "rate hotlinks" permission
- Verify JavaScript console for errors
- Check Drupal logs at Reports > Recent log messages

### Stars Not Displaying
- Ensure CSS/JS libraries loading properly
- Check for theme CSS conflicts
- Verify field display settings configured

## Development Notes

### For Production Use
Consider these improvements:

1. **Database Migration**: Replace state storage with proper tables:
```sql
CREATE TABLE hotlinks_ratings (
  id SERIAL PRIMARY KEY,
  node_id INT NOT NULL,
  user_id INT NOT NULL, 
  rating INT NOT NULL,
  created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_rating (node_id, user_id)
);
```

2. **User Rating Tracking**: Prevent duplicate ratings per user
3. **Advanced Moderation**: Custom workflow states
4. **Email Notifications**: Alert on new reviews
5. **Spam Protection**: Rate limiting and captcha

### API Functions
```php
// Check if user can rate
hotlinks_reviews_user_can_rate($node_id, $user_id);

// Get user's rating  
hotlinks_reviews_get_user_rating($node_id, $user_id);
```

## Uninstallation

When you disable this submodule:
- ❌ All review fields removed from hotlinks
- ❌ All rating and review data deleted (irreversible)
- ❌ Review settings removed from configuration
- ✅ Original hotlinks functionality fully restored

## License

GPL-2.0+ (same as main Hotlinks module)

## Support

Follow the same support guidelines as the main Hotlinks module. For issues:
1. Check troubleshooting section
2. Review Drupal logs  
3. Test with default theme
4. Ensure all requirements met