# Hotlinks

A comprehensive Drupal 11 module for creating and managing categorized link collections with hierarchical organization, thumbnails, and customizable display options.

## Features

### 🔗 **Link Management**
- **Custom Content Type**: Dedicated "Hotlink" content type for organizing links
- **Required URL Field**: Link field with support for external URLs and optional titles
- **Auto-Title Generation**: Automatically generates node titles from URLs when not provided
- **Rich Descriptions**: Optional long text field for detailed link descriptions

### 🗂️ **Hierarchical Categories**
- **Unlimited Hierarchy**: Create categories and subcategories to any depth
- **Category Descriptions**: Add descriptions to categories via taxonomy system
- **Multiple Categories**: Assign hotlinks to multiple categories
- **Smart Display**: Shows category counts including subcategory totals

### 🖼️ **Thumbnail Support**
- **Manual Upload**: Upload thumbnail images for any hotlink
- **Auto-Generation**: Automatically generate thumbnails from website screenshots
- **Multiple Services**: Choose from Thum.io, ScreenshotAPI, WebsiteShots, or HTML/CSS to Image
- **Fallback System**: Shows placeholder icon when no thumbnail available

### 🎨 **Flexible Display**
- **Custom View Mode**: "Hotlinks Index" display mode for category pages
- **Title Links to Node**: Clicking titles takes users to hotlink detail pages
- **External URL Separate**: Clear distinction between node links and external URLs
- **Responsive Design**: Mobile-friendly layout with clean styling

### ⚙️ **Admin Features**
- **Configuration Interface**: Comprehensive settings at `/admin/config/content/hotlinks`
- **Thumbnail Services**: Configure auto-thumbnail generation services
- **Display Options**: Control descriptions, link behavior, and category counts
- **Batch Operations**: Regenerate all thumbnails with progress tracking

## Requirements

- **Drupal**: 10 or 11
- **Core Modules**: node, field, taxonomy, link, text
- **PHP Extensions**: cURL (for auto-thumbnail generation)

## Installation

### Via Drush (Recommended)
```bash
# Place module files in modules/custom/hotlinks/
drush en hotlinks
drush cr
```

### Via Admin Interface
1. Place module files in `modules/custom/hotlinks/`
2. Go to Extend (`/admin/modules`)
3. Enable "Hotlinks" module
4. Clear cache

## Quick Start

### 1. Create Categories
1. Go to **Structure > Taxonomy > Hotlink Categories** (`/admin/structure/taxonomy/manage/hotlink_categories`)
2. Add your main categories (e.g., "Technology", "News", "Entertainment")
3. Add subcategories by setting the parent category when creating terms
4. Add descriptions to help users understand what belongs in each category

### 2. Configure Settings
1. Go to **Configuration > Content authoring > Hotlinks** (`/admin/config/content/hotlinks`)
2. Choose thumbnail service (Thum.io works immediately without setup)
3. Configure display options as needed
4. Save configuration

### 3. Add Your First Hotlink
1. Go to **Content > Add content > Hotlink** (`/node/add/hotlink`)
2. Enter a URL (title will auto-generate if not provided)
3. Select one or more categories
4. Add optional description
5. Upload thumbnail or let it auto-generate
6. Save

### 4. View Your Link Index
Visit `/hotlinks` to see your organized link collection!

## Usage

### Creating Categories
Categories support unlimited hierarchy depth:
```
Technology
├── Programming
│   ├── Languages
│   │   ├── PHP
│   │   ├── JavaScript
│   │   └── Python
│   └── Frameworks
├── Hardware
└── News
```

### Auto-Thumbnails
Configure automatic thumbnail generation:

1. **Thum.io** (Free, no setup required)
   - Just works out of the box
   - Good quality screenshots

2. **ScreenshotAPI** (Free tier + paid plans)
   - 50 free screenshots/month
   - High quality with customization
   - Get API key at screenshotapi.net

3. **HTML/CSS to Image** (Paid service)
   - Premium quality screenshots
   - Fast response times
   - Requires API key from htmlcsstoimage.com

### Custom Display Mode
The module creates a "Hotlinks Index" display mode that you can customize:

1. Go to **Structure > Content types > Hotlink > Manage display**
2. Click the **Hotlinks Index** tab
3. Customize field order, formatters, and visibility
4. Configure thumbnail image styles

## Public Pages

- **`/hotlinks`** - Main index showing all categories and subcategories
- **`/hotlinks/category/{id}`** - Individual category pages with links and subcategory navigation

## Permissions

Configure permissions at **People > Permissions**:

- **Access hotlinks index** - View the public link collection
- **Administer hotlinks** - Access configuration settings
- **Create hotlink content** - Add new hotlinks
- **Edit own/any hotlink content** - Modify existing hotlinks
- **Delete own/any hotlink content** - Remove hotlinks

## Submodules

### Hotlinks Star Trek Categories
An optional submodule that provides comprehensive Star Trek-themed categories:

```bash
drush en hotlinks_startrek
```

This adds 11 main categories with 2-9 subcategories each, perfect for Star Trek fan sites and link collections.

## Theming

### CSS Classes
The module provides CSS classes for theming:
- `.hotlinks-categories` - Main category container
- `.hotlinks-category` - Individual category sections
- `.hotlinks-subcategory` - Subcategory sections
- `.node--view-mode-hotlinks-index` - Custom display mode wrapper
- `.hotlink-thumbnail` - Thumbnail images
- `.hotlink-description` - Description text

### Template Overrides
You can override these templates in your theme:
- `node--hotlink.html.twig` - Individual hotlink pages
- `node--hotlink--hotlinks-index.html.twig` - Hotlinks index display mode

## Troubleshooting

### Thumbnails Not Generating
1. Check that cURL is enabled in PHP
2. Verify API keys are correct (if using paid services)
3. Check logs at **Reports > Recent log messages**
4. Try regenerating thumbnails in module settings

### Categories Not Showing
1. Ensure categories are published
2. Check permissions for viewing taxonomy terms
3. Verify categories are assigned to hotlinks
4. Clear cache: `drush cr`

### Custom View Mode Not Working
1. Go to **Structure > Display modes > View modes**
2. Ensure "Hotlinks Index" is enabled for content
3. Configure the display at **Structure > Content types > Hotlink > Manage display > Hotlinks Index**
4. Clear cache: `drush cr`

## Development

### API Functions
The module provides helper functions:
- `hotlinks_get_category_hierarchy($term_id)` - Get formatted hierarchy string
- `hotlinks_get_category_total_count($category_id)` - Get total count including subcategories

### Hooks
The module implements:
- `hook_entity_presave()` - Auto-title generation and thumbnail creation
- `hook_theme()` - Template definitions
- `hook_help()` - Help text for admin pages

## Changelog

### 1.0.0
- Initial release with hierarchical categories
- Custom hotlink content type with URL, categories, description fields
- Auto-thumbnail generation with multiple service options
- Custom "Hotlinks Index" display mode
- Responsive design with clean styling
- Comprehensive admin configuration
- Star Trek Categories submodule

## Support

For issues, feature requests, or questions:
1. Check the troubleshooting section above
2. Review Drupal logs at **Reports > Recent log messages**
3. Ensure all requirements are met
4. Clear cache and test in a clean environment

## License

GPL-2.0+

## Credits

Inspired by the classic Star Trek Wormhole link directory, modernized for Drupal 11 with contemporary features and best practices.