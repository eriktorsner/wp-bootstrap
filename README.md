# wp-bootstrap
Utils for bootstrapping a WordPress installations. Automates installation, configuration and content bootstrapping of WordPress installation.


## Installation

To add this package as a local, per-project dependency to your project, simply add a dependency on `eriktorsner/wp-bootstrap` to your project's `composer.json` file. Here is a minimal example of a `composer.json` file that just defines a dependency on PHP_Timer:

    {
        "require": {
            "eriktorsner/wp-bootstrap": "0.1.*"
        }
    }

**Note!:** wp-bootstrap assumes that wp-cli is globally available on the machine using the alias "wp". 


## Commands

wp-bootstrap exposes a few command that can be called from various automation envrionments

| Command | Arguments | Decription |
|---------|------------|------------|
| wp-install || Download and install WordPress core. Creates a default WordPress installation |
| wp-setup  || Add themes and plugins and import content |
| wp-bootstrap || Alias for wp-install followed by wp-setup|
| wp-update |none, "themes" or "plugins"| Updates core, themes or plugins that are installed from the WordPress repos |
| wp-export || Exports content from the WordPress database into text and media files on disk|
| wp-import || Imports content in text and media files on disk into the database. Updates existing pages / media if it already exists |
| wp-pullsettings || Helper: Adds a wpbootstrap section to the appsettings file (if it doesn't already exist) |

## Usage

wp-bootstrap is intended to be executed from a script or task runner. For instance Grunt, Gulp or Composer. 

**Sample Grunt setup (just showing one method):**

    grunt.registerTask('wp-export', '', function() {
        cmd = "php -r 'include \"vendor/autoload.php\"; Wpbootstrap\\Export::export();'";
        shell.exec(cmd);
    });

Run a method from the cli like this:
    $ grunt wp-export


**Sample composer setup:**

    "scripts": {
        "wp-bootstrap":"Wpbootstrap\\Bootstrap::bootstrap",
        "wp-install":"Wpbootstrap\\Bootstrap::install",
        "wp-setup":"Wpbootstrap\\Bootstrap::setup",
        "wp-update":"Wpbootstrap\\Bootstrap::update",
        "wp-export":"Wpbootstrap\\Export::export",
        "wp-import":"Wpbootstrap\\Import::import",
        "wp-pullsettings":"Wpbootstrap\\Appsettings::updateAppSettings"
    }

Run a method from the cli like this:

    $ composer wp-install
    $ composer wp-setup

wp-bootstrap can also be called straight from the command line (not encouraged):

    $ php -r 'include "vendor/autoload.php"; Wpbootstrap\Export::export();'



## Settings 

wp-bootstrap relies on 2 config files in your project root

**localsettings.json:**

    {
        "environment": "development",
        "url": "www.wordpressapp.local",
        "dbhost": "localhost",
        "dbname": "wordpress",
        "dbuser": "wordpress",
        "dbpass": "wordpress",
        "wpuser": "admin",
        "wppass": "admin",
        "wppath": "/vagrant/www/wordpress-default"
    
    }

The various settings in localsettings.json are self explanatory. This file is not supposed to be managed in source code control but rather be unique for each server where your WordPress site is installed (development, staging etc). 

**appsettings.json:**

    {
        "title": "Your WordPress site title",
        "plugins": {
            "standard": ["google-analyticator", "if-menu:0.2.1"],
            "local": ["myplugin"]
        },
        "themes": {
            "standard": ["twentyfourteen"],
            "local": ["mychildtheme"],
            "active": "mychildtheme"
        },
        "settings": {
            "blogname": "New title 2",
            "blogdescription": "The next tagline"
        },
        "wpbootstrap": {
            "posts": {
                "page": ["about","members"],
            },
            "menus": {
                "main": ["primary", "footer"]
            }
        }
    }

### Section: plugins:
This section consists of two sub arrays "standard" and "local". Each array contains plugin names that should be installed and activated on the target WordPress site. 

 - **standard** Fetches plugins from the official WordPress repository. If a specific version is needed, specify the version using a colon and the version identifier i.e **if-menu:0.2.1**
 - **local** A list of plugins in your local project folder. Plugins are expected to be located in folder projectroot/wp-content/plugins/. Local plugins are symlinked into place in the wp-content folder of the WordPress installation specified by wppath in localsettings.json

### Section: themes
Similar to the plugins section but for themes. 

 - **standard** Fetches themes from the official WordPress repository. If a specific version is needed, specify the version using a colon and the version identifier i.e **footheme:1.1**
 - **local** A list of themes in your local project folder. The themes are expected to be located in folder projectroot/wp-content/themes/. Local themes are symlinked into place in the wp-content folder of the WordPress installation specified by wppath in localsettings.json
 - **active** A string specifying what theme to activate.


### Section: settings

A list of settings that will be applied to the WordPress installation using the wp-cli command "option update %s". Currently only supports simple scalar values (strings and integers)

###Section: wpbootstrap

A list of content that can be serialized to disk using the wp-export method and later unserialized back into a WordPress install using the wp-import method.



