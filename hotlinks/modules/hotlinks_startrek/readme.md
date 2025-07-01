# Hotlinks Star Trek Categories

This submodule provides Star Trek Wormhole style categories for the Hotlinks module.

## Description

Replaces the basic example categories with comprehensive Star Trek-themed categories and subcategories to organize your Star Trek-related links.

## Installation

1. The main Hotlinks module will already be installed
2. Go to Extend (`/admin/modules`) 
3. Find "Hotlinks Star Trek Categories" under the Hotlinks package
4. Enable the module

Or via Drush:
```bash
drush en hotlinks_startrek
```

### If Enabling Both Modules at Once
If you enable both the main Hotlinks module and Star Trek Categories at the same time, the categories might not get created. If this happens:

```bash
# Run the update hook to create missing categories
drush updb
```

Or disable and re-enable the Star Trek module:
```bash
drush pmu hotlinks_startrek -y
drush en hotlinks_startrek -y
```

## What it does

- **Removes** the basic example categories (Technology, Reference, etc.)
- **Adds** 11 comprehensive Star Trek categories with 2-9 subcategories each
- **Preserves** any custom categories you may have already created
- **Preserves** all existing hotlinks (just updates their category organization)

## Uninstallation

When you disable this submodule:
- All Star Trek categories are removed
- Basic example categories are restored
- Your hotlinks remain but become uncategorized if they were in Star Trek categories
- Any custom categories you added are preserved

## Categories Included

- Star Trek Communities, Fandom, Games, International, News
- Star Trek Official, Reference, Role Playing, Services, Shopping, Universe

See the full list with descriptions in the module after installation.