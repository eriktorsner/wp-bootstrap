**0.5.0**
  - New feature: All object serialization is now in Yaml instead of serialized php objects. Note! This breaks backwards compatibility and is also the reason for bumping the version to 0.5.0
  - New feature: List and add posts, taxonomies and menus to appsettings.yml via new sub commands posts, taxonomies and menus

**0.4.0**
  - Major upgrade. WP Bootstrap now runs as a proper wp-cli sub command
  - New feature: localsettings.json is replaced with .env files
  - New feature: appsettings.json is replaced with appsettings.yml
  - New feature: support for dependencies between plugins/themes to ensure correct install order

**0.3.9**
  - Last version as a "stand alone" binary.
  - Skipping already installed themes and plugins (standard)

**0.3.8**
  - New feature: Support for running as a wp-cli sub command
  - Enhancement: snapshots command now includes column 'manage' in diff and show
  - Enhancement: improved column names snapshots in diff and show

**0.3.7**
  - New feature: Support for dependencies between themes/plugins to determine installation order
  - Bug fix: During import, importing a menu and theme_mods would reset theme modifications.
  - Enhancement: Logging (DEBUG level) output from external commands (wp-cli, rm, cp, ln etc).

**0.3.6**
  - New feature: Support for extensions
  - Better media extraction from content, now finding images in serialized/base64 encoded content
  - Improved performance on imports

**0.3.6**
  - New feature: Support for extensions
  - Better media extraction from content, now finding images in serialized/base64 encoded content
  - Improved performance on imports

**0.3.5**

  - Bug fix: (major) Fixed issue when importing two posts with same slug but different post types

**0.3.4**

  - Bug fix: exporting now also includes posts with status = inherit
  - Bug fix: importing a post where the parent post is missing doesn't create infinite loop

**0.3.3**

  - new feature: adding configured symlinks during wp-init

**0.3.2**

  - new feature: wp-snapshots command to manage options
  - Code cleanup, more PSR2 strict

**0.3.1**

  - Bug fixes for exporting and importing taxonomies of type "postid"
  - wp-init generates a wp-cli.yml file if localsettings/wppath has non default value

**0.3.0**

  - Reference section is moved out from "content" into it's own section in appsettings.json
  - Added handling for "postid" taxonomies
  - Creating manifest files for taxonomies for better import control
  - Fixed some issues with search/replace in options and metadata
  - Neutralizing (urls) settings handled via wp-cfm
  - Additional refactoring
  - Logging all system calls done via PHP exec()

**0.2.9**

  - Refactored and renamed classes
  - Introduced class Container as a (sort of) dependency injection container
  - Brought test coverage up to 85%

**0.2.8**

  - Renamed section "wpbootstrap" to "content" in appsettings.json
  - Lots of logging added to the debug level.
  - Fixed bugs found from unit testing.
  - Brought test coverage back up to 80%

**0.2.7**

  - When exporting, all taxonomy terms that are referenced by a post will be included. Better taxonomy handling (assignment) when importing the terms
  - Improved import of Posts
  - Added Monolog as a dependency
  - Logging to console and file can be configured via localsettings

**0.2.6**

  - Improves handling for media that are not images (zip files etc).


**0.2.5**

  - Simplified BASEPATH heuristics
  - When exporting, missing media files does not generate an error message

**0.2.4**

  - Added VERSION constant.
  - Improvements for being called from within a WordPress plugin (such as Wp-bootstrap-ui)

**0.2.3**

  - Referenced media handled better, so media that is referenced (used) in posts and widgets are included even if they are not properly attached
  - Code style cleanup using php-cs-fixer.

**0.2.2**

  - Support for ***references***. Possible to add names of options that are references to other posts or taxonomy terms.
  - Fixed issues found when  Test coverage up to over 80%.


**0.2.1**

 - Support for taxonomy terms
