# Daily Quotes WordPress Plugin

A WordPress plugin that displays non-repeating daily text or HTML content from named sets, with both Gutenberg block and shortcode support.

## Features

- **Daily Quote Sets**: Create named sets of quotes/text content
- **Smart Rotation**: No repeats until all items in a set are shown, then cycles restart
- **Per-Day Pinning**: Option to pin one item per day site-wide
- **Gutenberg Block**: Easy-to-use block with visual editor controls
- **Shortcode Support**: `[daily_quotes]` shortcode for any post/page
- **Drag & Drop Ordering**: Reorder items with drag-and-drop in admin
- **Auto-Assignment**: New items automatically assigned to "Default set"
- **Interactive Admin**: Toggle "Shown" status to manually control rotation
- **Hidden Date Shortcode**: `[thedate]` shortcode for current date display
- **CSS Class Support**: Add custom CSS classes to blocks

## Installation

1. Download the plugin files
2. Upload to `/wp-content/plugins/daily-quotes/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to **Dailies** in your admin menu to start creating sets and quotes

## Usage

### Creating Content

1. **Create a Set**: Go to Dailies → Daily Sets → Add New Set
2. **Add Quotes**: Go to Dailies → Daily Items → Add New Item
   - Enter your text/HTML content
   - Assign to a set using the dropdown
   - Use the Order field to arrange items
3. **Reorder**: Drag and drop items in the Daily Items list

### Using the Block

1. Add the "Daily Quote" block to any post/page
2. Select your set from the dropdown (auto-selects "Default set" if available)
3. Configure options:
   - **Randomize**: Pick randomly among remaining items
   - **Pin One Per Day**: Same item for entire day site-wide
4. Add custom CSS classes if needed

### Using the Shortcode

```
[daily_quotes set="Your Set Name" randomize="1" per_day="1"]
```

**Parameters:**
- `set`: Set name or ID (required)
- `randomize`: 1 for random, 0 for sequential (default: 1)
- `per_day`: 1 to pin per day, 0 to rotate on each render (default: 1)

### Date Shortcode

```
[thedate]
[thedate format="dd.mm.YY"]
[thedate format="YYYY-mm-dd"]
```

## How It Works

### Rotation Logic

- **No Repeats**: Items won't repeat until all items in the set are shown
- **Cycle Restart**: Once all items are shown, the cycle starts over
- **Per-Day Mode**: When enabled, the same item is shown all day (uses site timezone)
- **Global State**: Rotation state is stored per set, not per user
- **Manual Control**: Admin can toggle "Shown" status to reset items back into rotation

### Admin Interface

- **Dailies Menu**: Top-level admin menu with Daily Items and Daily Sets
- **Visual Feedback**: "Shown" checkboxes indicate which items have been displayed
- **Interactive Controls**: Click checkboxes to manually mark items as shown/unshown
- **Set Filtering**: Filter items by set in the admin list
- **Drag & Drop**: Reorder items by dragging rows up/down
- **Auto-Ordering**: New items get the next available order number

## File Structure

```
daily-quotes/
├── daily-quotes.php          # Main plugin file
├── includes/
│   ├── cpts.php             # Custom post types and admin UI
│   ├── rotation.php         # Core rotation logic
│   ├── shortcode.php        # Shortcode handlers
│   ├── block.php            # Gutenberg block
│   └── rest.php             # REST API endpoints
├── assets/
│   └── admin-order.js       # Admin drag-and-drop functionality
└── README.md                # This file
```

## Requirements

- WordPress 5.0+ (for Gutenberg block support)
- PHP 7.4+

## Changelog

### Version 0.2.0
- Added Gutenberg block with visual editor controls
- Implemented drag-and-drop ordering in admin
- Added interactive "Shown" status indicators with AJAX toggling
- Created "Default set" auto-assignment
- Added `[thedate]` shortcode
- Improved admin menu structure
- Added CSS class support
- Enhanced rotation logic with per-day pinning

### Version 0.1.0
- Initial release
- Basic shortcode functionality
- Custom post types for sets and items
- Core rotation logic

## Support

For issues, feature requests, or questions, please create an issue on GitHub.

## License

GPL-2.0+