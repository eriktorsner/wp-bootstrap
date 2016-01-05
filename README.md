
# wp-bootstrap
Utils for bootstrapping a WordPress installations. Automates installation, configuration and content bootstrapping of WordPress installation.

[Core concepts and intended workflow](doc/01-intro.md)

[Tutorial on wpessentials.io](http://www.wpessentials.io/2015/12/preparing-a-wordpress-site-for-git-using-wp-bootstrap/)

## Installation

To add this package as a local, per-project dependency to your project, simply add a dependency on `eriktorsner/wp-bootstrap` to your project's `composer.json` file. Here is a minimal example of a `composer.json` file that just defines a dependency wp-bootstrap:

    {
        "require": {
            "eriktorsner/wp-bootstrap": "0.3.*"
        }
    }

**Note!:** wp-bootstrap assumes that wp-cli is globally available on the machine using the alias "wp". 

### Quick start

Wp-bootstrap can be called directly from it's binary, located in vendor/bin. To reduce typing, you can add the bootstrap commands to your composer file:

    $ vendor/bin/wpbootstrap wp-init-composer
    
Then to run a command:

    $ composer wp-export
    

## Commands

wp-bootstrap exposes a few command that can be called from various automation environments

| Command | Arguments | Description |
|---------|------------|------------|
| wp-init-composer || Add Wp-Bootstrap bindings to composer.json |
| wp-install || Download and install WordPress core. Creates a default WordPress installation |
| wp-setup  || Add themes and plugins and import content |
| wp-bootstrap || Alias for wp-install followed by wp-setup|
| wp-update |none, "themes" or "plugins"| Updates core, themes or plugins that are installed from the WordPress repository |
| wp-export || Exports content from the WordPress database into text and media files on disk|
| wp-import || Imports content in text and media files on disk into the database. Updates existing pages / media if it already exists |
| wp-snapshots || Utils for diffing WordPress options |
|    -"- |list | Shows all currently available snapshots  |
|    -"- |snapshot $name $comment | Create a new snapshot with $name (optional) and $comment (optional)  |
|    -"- |diff $name  | Compare snapshot $name with current WordPress options  |
|    -"- |diff $name $name2  | Compare 2 snapshots  |
|    -"- |show $name  | List all option with values in snapshot $name  |
|    -"- |show $name $option  | Display a single option from snapshot $name  |

## Usage

wp-bootstrap is intended to be executed from a script or task runner like Grunt, Gulp or Composer. It can be called directly from the command line or as part of a larger task in i.e Grunt:

**Command line usage:**

    $ vendor/bin/wpbootstrap wp-export
    $ vendor/bin/wpbootstrap wp-update plugins


**Grunt usage:**
Sample Grunt setup (just showing two methods):

    grunt.registerTask('wp-export', '', function() {
        cmd = "vendor/bin/wpbootstrap wp-export";
        shell.exec(cmd);
    });

    grunt.registerTask('wp-update', '', function() {
        cmd = "vendor/bin/wpbootstrap wp-update";
        if (typeof grunt.option('what') != 'undefined') cmd += ' ' + grunt.option('what');
        shell.exec(cmd);
    }); 

Then run your grunt task like this:

    $ grunt wp-export
    $ grunt wp-update --what=plugins



**Composer usage:**
Wp-bootstrap can be added to your composer.json if you prefer to use composer as a task runner. You can manually edit your composer.json to include be below script entries:

    "scripts": {
        "wp-bootstrap": "vendor\/bin\/wpbootstrap wp-bootstrap",
        "wp-install": "vendor\/bin\/wpbootstrap wp-install",
        "wp-setup": "vendor\/bin\/wpbootstrap wp-setup",
        "wp-update": "vendor\/bin\/wpbootstrap wp-update",
        "wp-export": "vendor\/bin\/wpbootstrap wp-export",
        "wp-import": "vendor\/bin\/wpbootstrap wp-import",
        "wp-pullsettings": "vendor\/bin\/wpbootstrap wp-updateAppSettings",
        "wp-init": "vendor\/bin\/wpbootstrap wp-init",
        "wp-init-composer": "vendor\/bin\/wpbootstrap wp-initComposer"
    }

Or, have wp-bootstrap edit your composer for you:

    $ vendor/bin/wpbootstrap wp-init-composer


Then run a method from the cli like this:

    $ composer wp-install
    $ composer wp-update plugins    



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
            "standard": [
                "google-analyticator",
                "if-menu:0.2.1"
            ],
            "local": [
                "myplugin"
            ]
        },
        "themes": {
            "standard": [
                "twentyfourteen"
            ],
            "local": [
                "mychildtheme"
            ],
            "active": "mychildtheme"
        },
        "settings": {
            "blogname": "New title 2",
            "blogdescription": "The next tagline"
        },
        "content": {
            "posts": {
                "page": [
                    "about",
                    "members"
                ]
            },
            "menus": {
                "main": [
                    "primary",
                    "footer"
                ]
            },
            "taxonomies": {
                "category": "*"
            }
        },
        "references": {
            "posts": {
                "options": [
                    "some_setting",
                    {
                        "mysettings": "->term_id"
                    },
                    {
                        "mysettings2": "[2]"
                    },
                    {
                        "mysettings3": [
                            "->term_id",
                            "->other_term_id"
                        ]
                    }
                ]
            }
        }
    }

### Section: plugins:
This section consists of two sub arrays "standard" and "local". Each array contains plugin names that should be installed and activated on the target WordPress site. 

 - **standard** Fetches plugins from the official WordPress repository. If a specific version is needed, specify the version using a colon and the version identifier i.e **if-menu:0.2.1**
 - **local** A list of plugins in your local project folder. Plugins are expected to be located in folder projectroot/wp-content/plugins/. Local plugins are symlink'ed into place in the wp-content folder of the WordPress installation specified by wppath in localsettings.json

### Section: themes
Similar to the plugins section but for themes. 

 - **standard** Fetches themes from the official WordPress repository. If a specific version is needed, specify the version using a colon and the version identifier i.e **footheme:1.1**
 - **local** A list of themes in your local project folder. The themes are expected to be located in folder projectroot/wp-content/themes/. Local themes are symlinked into place in the wp-content folder of the WordPress installation specified by wppath in localsettings.json
 - **active** A string specifying what theme to activate.


### Section: settings

A list of settings that will be applied to the WordPress installation using the wp-cli command "option update %s". Currently only supports simple scalar values (strings and integers)

###Section: content

This sections defines how to handle content during export and import of data using the wp-export or wp-import command. 

**posts** Used during the export process. Contains zero or more keys with an associated array. The key specifies a post_type (page, post etc) and the array contains **post_name** for each post to include. The export process also includes any media (images) that are attached to the specific post.

**menus** Used during the export process. Contains zero or more keys with an associated array. The key represents the menu name (as defined in WordPress admin) and the array should contain each *location* that the menu appears in. Note that location identifiers are unique for each theme.

**taxonomies** Used during the export process. Contains zero or more keys with either a string or array as the value. Use asterisk (*) if you want to include all terms in the taxonomy. Use an array of term slugs if you want to only include specific terms from that taxonomy.

###Section: references
Used during the import process. This is a structure that describes option values (in the wp_option table) that contains references to a page or a taxonomy term. The reference item can contain a "posts" and a "terms" object describing settings that points to either posts or taxonomy terms. Each of these objects contains one single member "options" referring to the wp_options table (support for other references will be added later). The "options" member contains an array with names of options in the wp_option table. There are three ways to refer to an option:

  - **1.** A simple string, for instance "page_on_front". Meaning that there is an option in the wp_options table named "page_on_front" and that option is a reference to a post ID.
  - **2.** An object with a single name-value pair, for instance {"mysetting": "[2]"} or {"mysetting2":"->page_id"} meaning:
    -  There is an option in the wp_options table named "mysetting"
    - That setting is an array or object and the value tells wp-bootstrap how to access the array element or member variable of interest. The value follows PHP syntax, so an array element is accessed via "[]" notation and an object member variable is accessed via the "->" syntax.
  - **3.** As above, but instead of a simple string value, the value is an array of strings.

Reference resolving will only look at the pages/posts/terms included in your import set.  The import set might include an option "mypage" in the config/wpbootstrap.json file that points to post ID=10. Also in the import set, there is that page with id=10. When this page is imported in the target WordPress installation, it might get another ID, 22 for instance. By telling wp-bootstrap that the setting "mypage" in the wp_options table refers to a page, wp-bootstrap will update that option to the new value 22 as part of the process.

###Parent child references and automatic includes

Wp-bootstrap tries it's hardest to preserve references between exported WordPress objects. If you export a page that is the child of another page, the parent page will be included in the exported data regardless if that page was included in the settings. Similar, if you export a menu that points to a page or taxonomy term was not specified, that page and taxonomy term will also be included in the exported data. 

###Import matching
When importing into a WordPress installation, wp-bootstrap will use the **slug** to match pages, menus and taxonomy terms. So if the dataset to be imported contains a page with the **slug** 'foobar', that page will be (a) created if it didn't previously exist or (b) updated if it did. The same logic applies to posts (pages, attachments, posts etc), menu items and taxonomy terms.

**Note:** Taxonomies are defined in code rather than in the database. So the exact taxonomies that exist in a WordPress installation are defined at load time. The built in taxonomies are always there, but some taxonomies are defined in a theme or plugin. In order for your taxonomy terms to be imported during the wp-import process, the theme or plugin that defined the taxonomy needs to exist.

## Snapshots

The wp-snapshot command saves a snapshot of (almost) all settings in the WordPress options table. The file is saved in bootstrap/config/snapshots. The idea is that a snapshot can be later be compared, with the current values in the options table or between two snapshots. This can be used as a tool to quickly identify which options that are modified and need to go into the WP-CFM configuration.

Besides an actual snapshot of the options table, a snapshot also contains an optional comment, creation date, environment as stated in localsettings and the host name of the computer that the snapshot was created on.

### Subcommands
**snapshot**
Create a snapshot with the current unix timestamp as name
    $ composer wp-snapshots snapshot

Create a snapshot named foobar

    $ composer wp-snapshots snapshot foobar

Create a snapshot named foobar2 with a comment

    $ composer wp-snapshots snapshot foobar2 "after installing plugin fuubar"

**diff**
Show the diff between an existing snapshot and the current state of WordPress

    $ composer wp-snapshots diff foobar

Show the diff between two existing snapshots

    $ composer wp-snapshots diff foobar foobar2

**list**
List all current snapshots

    $ composer wp-snapshots list

**show**
Show all options in the snapshot foobar:

    $ composer wp-snapshots show foobar

When showing all options in a snapshot, structs and arrays are converted to a string using print_r. All values are truncated at 40 character. So the content of long strings and complex structs/arrays will typically be truncated. To show an the option widget_archives individual option in it's entirety:

    $ composer wp-snapshots show foobar widget_archives

  
## Testing

Since wp-bootstrap relies a lot on WordPress, there's a separate Github repository for testing using Vagrant. The test repo is available at [https://github.com/eriktorsner/wp-bootstrap-test](https://github.com/eriktorsner/wp-bootstrap-test).

## Contributing

Contributions are welcome. Apart from code, the project is in need of better documentation, more test cases, testing with popular themes and plugins and so on. Any type of help is appreciated.

## Version history

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

