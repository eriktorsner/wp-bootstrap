# Using wp-bootstrap

- [Installation](#installation)
- [Core Concepts](#core-concepts)
- [Intended workflow](#intended-workflow)


## Installation

wp-bootstap is available on Packagist ([eriktorsner/wp-bootstrap](https://packagist.org/packages/eriktorsner/wp-bootstrap))
and as such installable via [Composer](http://getcomposer.org/).

```bash
composer require eriktorsner/wp-bootstrap
```

## Core Concepts

Wp-bootsrap is a util for bootstrapping a WordPress installations. It's intended use is to automate installation, configuration and content bootstrapping in a safe and scriptable way.

First, wp-bootstrap enables you to script an entire WordPress installation with themes and plugins by writing two configuration files. (1) localsettings.json where you provide details about the server environment and (2) appsettings.json where you provide information about what plugins and themes to install. This makes it possible to quickly create identical installation in development, test, staging or other environments.

The second, perhaps more important part, is that wp-bootstrap provides methods to export and import content and settings.

Exported data is stored in text files suitable for both source code control and distribution. When the export is created, wp-bootstrap aims to include everything that is needed to recreate the content on another WordPress installation. When exporting a menu item that is pointing to a taxonomy term, that taxonomy term will be included in the export. When exporting a page that uses a thumbnail, that item in the media library will be included etc.

When data is imported to the target environment, wp-bootstrap imports related media, creates (or updates) existing taxonomy terms etc. so that every item looks and works the same.

In addition to normal content, wp-bootstrap also exports and imports settings from the wp_options table. Settings are also stored in a file. Wp-bootstrap also resolves references embedded in the wp_options table so that any individual option that points to a page or a post (like "page_on_front") will be adjusted after import, making sure that the correct database id is used.

Wp-bootstrap depends on [wp-cli](http://wp-cli.org/) and a plugin named [WP-CFM](https://wordpress.org/plugins/wp-cfm/) to do a lot of the heavy lifting under the hood. The ideas and rationale that inspired this project was originally presented on [my blog](http://wpessentials.io/blog) and  in the book [WordPress DevOps](https://leanpub.com/wordpressdevops) available on [Leanpub](https://leanpub.com/wordpressdevops).  Wp-bootsrap also assumes that you are using Composer even if it's not strictly needed. Installation of WP-CFM is easiest to do via wp-bootstrap itself but installation of wp-cli needs to be done separately.

This project scratches a very specific WordPress itch: being able to develop locally, managing the WordPress site in Git and being able to push changes (releases) to a production environment without worrying about overwriting content or having to manually migrate any setting or content.

#Intended workflow

...to be 
