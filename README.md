
# wp-bootstrap
Wp-cli subcommand for managing WordPress installations. Automates installation, configuration and importing/exporting content.

The central idea with WP Bootstrap is to provide a tool that makes it easier to create a decent WordPress deployment workflow. WP Bootstrap lets you use configuration files and a few command line commands to install WordPress, set it up with the correct plugins and themes, import options as well as pages, posts, menus etc. To top it of, it also maintains ID integrity during import so that the site looks the same when it's imported as it did when it was exported, even if all the underlying post ID's have changed.

Tutorial for pre-subcommand usage (before 0.4.0):
[Tutorial on wpessentials.io](http://www.wpessentials.io/2015/12/preparing-a-wordpress-site-for-git-using-wp-bootstrap/)

## Installation

To add this package as a local, per-project dependency to your project, simply add a dependency on `eriktorsner/wp-bootstrap` to your project's `composer.json` file. Here is a minimal example of a `composer.json` file that just defines a dependency wp-bootstrap:

    {
        "require": {
            "eriktorsner/wp-bootstrap": "~0.5.0"
        }
    }

or from the command line:

    $ composer require eriktorsner/Wp-bootstrap

Once WP Bootstrap is installed, wp-cli needs to know about it. Find (or create) your wp-cli.yml config file and add:

    require:
      - vendor/autoload.php

## Configuration files

WP Bootstrap uses configuration files to control the installation of WordPress as well as plugins and themes.

| File             | Format | Descriptions                                                                                             |
|:-----------------|:-------|:---------------------------------------------------------------------------------------------------------|
| appsettings.yml  | Yaml   | Defines plugins, themes and import/export settings                                                       |
| .env             | Dotenv | Environment variables needed to install WordPress                                                        |
| .env-development | Dotenv | Optional. Environment variable for the development environment                                           |
| .env-test        | Dotenv | Optional. Environment variable for the test environment                                                  |
| wp-cli.yml       | Yaml   | The standard wp-cli config file. Sub command setenv manages path and writes the environment in this file |

## Quick introduction. Installing and setting up WordPress using WP Bootstrap

**Step 1: Creating .env files**

As a first step, create a .env file in your project root with settings matching your local database and apache/nginx configuration. Note that since WP Bootstrap maintains settings file in the project root folder, it's recommended to install WordPress in different location like a sub folder.

    # file: .env
    wppath=/path/to/target/wordpress
    wpurl=www.example.com
    dbhost=localhost
    dbname=wordpress
    dbuser=wordpress
    dbpass=secret
    wpuser=admin
    wppass=anothersecret

Optionally, create an 'overlay' .env file for a specific environment, i.e 'development':

    # file: .env-development
    wppath=/path/to/target/wordpress-dev
    wpurl=dev.example.com
    dbname=wordpress-dev

Update the wp-cli.yml file with path and environment info:

    $ wp setenv standard

Or (if you created a .env-development):

    $ wp setenv development

**Step 2: Create an appsettings.yml file (optional)**

Create the appsettings.yml file that defines this WordPress installation

    # file: appsettings.yml
    # set a title for the WordPress site (defaults to '[title]')
    title: Testing Bootstrap

    # keep default content such as themes, posts, pages etc.
    keepDefaultContent: true

    # add a few plugins from the repo
    plugins:
      standard:
        - wp-cfm
        - disable-comments:1.3

    content:
      posts:
        page: '*'

**Step 3: Install WordPress**

Install WordPress

    $ wp bootstrap Install

...and install plugins etc.

    $ wp bootstrap setup

Once WordPress is up and running, you will typically run the setup command over and over again as you add config to the appsettings.yml

**Step 4: Exporting and importing content**

To export the content defined:

    $ wp bootstrap export

... and to import it back again:

    $ wp bootstrap import

To make a quick test. Use the appsettings.yml defined in step 2 above and execute the export command. The defined content (all pages) will be serialized and stored under the bootstrap subfolder in your project root. Edit (or even delete) the default sample page in WordPress and run the import command. The page should then be completely restored.

## Importing and exporting content

The WP Bootstrap export command will take all content defined in appsettings.yml and serialize it to disk. The target folder is 'bootstrap' in your project root. This folder is supposed to be managed in Git so that it can be easily transferred to a another environment. As you might note, not all content in a WordPress installation is exported, actually it's the opposite, as little as possible but enough to maintain a working site. The idea is that you only export the content that you deem are part of you _application_ meaning for instance the static front page, the menu structure, all the content in various about pages and terms and conditions and anything similar. When the site is deployed to production, the same content will be imported there.

The benefit of this might not be apparent when you deploy the site the first time. But when the site have been live for a few weeks and it's time to overhaul the menu structure and make a few changes to the About Us page, the benefits will become more apparent. At this time the production site might have lots of new blog posts, even more comments and perhaps even e-commerce orders. You don't want to make a complete database migration from your development environment because that would destroy all this other content. But by using WP Bootstrap you can import and overwrite just exactly those pieces of content that are defined in the appsettings.yml file. No other content on the target site gets touched.

Besides just exporting and importing content. WP Boostrap does three other things:

  - It keeps track of the internal integrity of the content. A page with ID 10 in your development environment might end up with ID 223 in the production environment. If you have a menu that points to page ID 10 in the exported content, that menu will be modified to point to ID 223 when it's imported into production.
  - It keeps track of parent/child relationships. If the exported page with ID 10 is a child page of page 45, page 45 will be included in the exported content as well, otherwise the parent/child relationship would break
  - It keeps an eye on media. If an exported page uses a featured image and two more images are used in the post content, WP Bootstrap identifies these images and includes them in the exported data.


### Managing WordPress options

WP Bootstrap is tightly integrated with the plugin WP-CFM. It's not mandatory to use it and you'll have to specify WP-CFM manually among the plugins in appsettings.yml. When exporting content, WP Bootstrap will use WP-CFM to export options defined in a WP-CFM bundle named 'wpbootstrap'. The resulting json file is copied to the bootstrap/config sub folder and should be managed under git.

At import, that file is copied back into the expected location under wp-content and pushed back into the WordPress database.

The actual management of what options to include is managed using the WP-CFM UI in the WordPress admin area. [Read more about WP-CFM here](https://wordpress.org/plugins/wp-cfm/).

To help you identify what options to include and which ones that are changed, please refer to the section about the optiosnap command below.


## Available commands

WP Bootstrap adds the following commands to wp-cli:

### Subcommand bootstrap

The below commands are executed using:

    $ wp bootstrap <command>

| Command | Arguments | Description                                                                      |
|:--------|:----------|:---------------------------------------------------------------------------------|
| install |           | Add Wp-Bootstrap bindings to composer.json                                       |
| setup   |           | Download and install WordPress core. Creates a default WordPress installation    |
| export  |           | Add themes and plugins and import content                                        |
| import  |           | Alias for wp-install followed by wp-setup                                        |
| reset   |           | Updates core, themes or plugins that are installed from the WordPress repository |


### Subcommand setenv

Setenv is a separate command that updates the wp-cli.yml file with settings from the .env files

| Command | Arguments          | Description                                                  |
|:--------|:-------------------|:-------------------------------------------------------------|
| setenv  | <environment name> | Updates the variables 'path' and 'environment' in wp-cli.yml |

*Note:* setenv uses the output from wp --info to determine name and location of the project specific wp-cli.yml file

### Subcommand optionsnap

Optionsnap is a utility that helps you keep track of which WordPress options that are in use in the WordPress installation. It's essentially dumping the contents of the wp_options table into a file located in sub folder bootstrap/snapshots. It's mainly intended to help developers understand which option values that are modified between two points in time.

The below commands are executed using:

    $ wp optionsnap <command> <args>

| Command | Arguments            | Description                                                                                        |
|:--------|:---------------------|:---------------------------------------------------------------------------------------------------|
| snap    | <name> <--comment>   | Creates a new snapshot file with an optional comment                                               |
| list    |                      | Lists all available snapshots                                                                      |
| show    | <name>               | Lists alls options values in the snapshot named <name>                                             |
| show    | <name> <option_name> | Shows the option identified by <option_name> in the snapshot <name>                                |
| diff    | <name>               | Shows the diff between the current state of the wp_options table the snapshot identified by <name> |
| diff    | <name> <name2>       | Shows the diff between the two snapshots identified by <name> and <name2>                          |


## Settings

### Dotenv files

Settings that are unique to a specific WordPress installation environment are kept in dotenv files. These settings are only used when installing WordPress for the first time. To make it easier to maintain multiple WordPress installs on the same physical or virtual machine, one dotenv file can overlay the base one.

When executing the install command, WP Bootstrap first looks in the wp-cli.yml file to read the name of the current environment. In the next step, it reads the variables contained in the base dotenv file (.env). In the last step, it looks for a file named .env-<environment name> and if that file exists, it is also read and parsed. For values that are found in both the base and the environment specific file, the value from the environment specific one takes precedence.

| Variable | Description                                                                                                        |
|:---------|:-------------------------------------------------------------------------------------------------------------------|
| wppath   | The path where WordPress should be installed. Also used by setenv to update wp-cli.yml when switching environments |
| wpurl    | The URL for the WordPress installation. Determined by the web server settings                                      |
| dbhost   | The host name for the MySql database                                                                               |
| dbname   | The database name for the MySql database                                                                           |
| dbuser   | MySql user name                                                                                                    |
| dbpass   | MySql user password                                                                                                |
| wpuser   | Default WordPress user name (often 'admin')                                                                        |
| wppass   | Password for the above WordPress user                                                                              |


### Application settings

The Application settings file, appsettings.yml defines WP Bootstraps behavior when installing plugins and themes and when importing and exporting data to and from WordPress.

The settings file consists of several sections:

#### Base parameters:
- **title** Specifies the title/blogname for the new WordPress installation. Used during bootstrap install
- **version** (optional). Specifies the WordPress core version to install. If not specified (recommended) the latest version is installed
- **keepDefaultContent** (optional). If not set or if set to false, all default content and all themes and plugins are removed after initial installation. To keep the default content, set this parameter to true.

#### Plugins

### Section: plugins

This section consists of up to three sub sections named "standard", "local" and "localcopy". In each section, a list of plugins is defined. Plugins that are available in the WordPress plugin repository or via a URL are defined in the "standard" section. Plugins that exists locally in the same Git repository as the project are defined in the "local" section and are linked into the target WordPress install using a symlink. Some plugins won't behave very well as a symlink and they need to be copied into the correct place, these plugins are defined in the "localcopy" section. The "local" and "localcopy" sections are mostly used for premium plugins that can't be installed via the standard WordPress repository.

Each of the three plugin sections define a list. In it's simplest form, the list is just a string that identifies the slug of the plugin and an optional version like shown here:

    plugins:
      standard:
        - akismet
        - woocommerce
        - google-sitemap-generator:4.0.6
      local:
        - mycustomplugin


In some (rare) cases, a plugin can't be installed unless another plugin or theme is already installed. To handle this, the plugin list can also contain an object with some optional extra parameters. To ensure that a specific plugin isn't installed before another one:

    plugins:
      standard:
        - akismet
        - woocommerce:
            requires:
              plugins: ['google-sitemap-generator']
              themes: ['mycustomtheme']
        - google-sitemap-generator        
      local:
        - mycustomplugin

In this example, the WooCommerce plugin will not be installed until after Google Sitemap Generator and the theme Mycustomthme are installed.

For standard plugins, the slug refers to the unique identifier (last part of the URL) in the WordPress repository. If the slug is a full URL, the plugin will be installed from that URL. But for local and localcopy plugins, the slug refers to the folder name that the plugin is stored in on the local file system. The above definition would assume a project file tree that looks something like this:

    .
    └── wp-content
        ├── plugins
        │   └── mycustomplugin
        │       ├── file1.php
        │       └── file2.php
        └── themes
            └── mycustomtheme
                ├── functions.php
                └── style.css


When defining a plugin as an object rather than a simple string. The object can have the following properties:

 - **slug** The name/slug of the plugin (will override the key name used in the list)
 - **version** Specifying a version when installing a plugin from the official WordPress repository,
 - **requires** Two lists of slugs identifying other themes and plugins that needs to be installed first
   - **plugins** An array of slugs (string) that defines required plugins
   - **themes** An array of slugs (string) that defines required themes


### Section: themes
Similar to the plugins section but for themes. The only real difference is that the themes section also has the parameter 'active' that identifies the theme that should be activated.

- **standard** Fetches themes from the official WordPress repository. If a specific version is needed, specify the version using a colon and the version identifier i.e **footheme:1.1**
- **local** A list of themes in your local project folder. The themes are expected to be located in folder projectroot/wp-content/themes/. Local themes are symlinked into place in the wp-content folder of the WordPress installation specified by wppath in localsettings.json
- **localcopy** A list of themes in your local project folder. The themes are expected to be located in folder projectroot/wp-content/themes/. Local themes are copied into place in the wp-content folder of the WordPress installation specified by wppath in localsettings.json. Some poorly written themes and plugins requires to be located in the correct WordPress folder, but consider it as a last option
- **active** A string specifying what theme to activate.


### Section: settings

A list of options (settings) that will be applied to the WordPress installation using the wp-cli command "option update %s". Currently only supports simple scalar values (strings and integers). Example:

    settings:
      admin_email: foobar@example.com
      blogname: My first blog
      blogdescription: Just anohter blog tagline

For the most part, it's recommended to manage options using the WP-CFM plugin. WP Bootstrap has build in support for importing and exporting options using WP-CFM. Managing options directly in the appsettings.yml can quickly become overwhelming.


### Section: content

This sections defines how to handle content during export and import of data using the wp-export or wp-import command.

**posts** Used during the export process. Contains zero or more keys with an associated array. The key specifies a post_type (page, post etc) and the array contains **post_name** for each post to include. The export process also includes any media (images) that are attached to the specific post or are referred to in the post content or post meta.

**menus** Used during the export process. Contains zero or more keys with an associated array. The key represents the menu name (as defined in WordPress admin) and the array should contain each *location* that the menu appears in. Note that location identifiers are unique for each theme.

**taxonomies** Used during the export process. Contains zero or more keys with either a string or array as the value. Use asterisk (\*) if you want to include all terms in the taxonomy. Use an array of term slugs if you want to only include specific terms from that taxonomy.


### Section: references
Used during the import process. This is a structure that describes option values (in the wp_option table) that contains references to a page or a taxonomy term. The reference item can contain a "posts" and a "terms" object describing settings that points to either posts or taxonomy terms. Each of these objects contains one single member "options" referring to the wp_options table (support for other references will be added later). The "options" member contains an array with names of options in the wp_option table. There are three ways to refer to an option:

  - **1.** A simple string, for instance "page_on_front". Meaning that there is an option in the wp_options table named "page_on_front" and that option is a reference to a post ID.
  - **2.** An object with a single name-value pair, for instance {"mysetting": "[2]"} or {"mysetting2":"->page_id"} meaning:
    -  There is an option in the wp_options table named "mysetting"
    - That setting is an array or object and the value tells wp-bootstrap how to access the array element or member variable of interest. The value follows PHP syntax, so an array element is accessed via "[]" notation and an object member variable is accessed via the "->" syntax.
  - **3.** As above, but instead of a simple string value, the value is an array of strings.

Reference resolving will only look at the pages/posts/terms included in your import set.  The import set might include an option "mypage" in the config/wpbootstrap.json file that points to post ID=10. Also in the import set, there is that page with id=10. When this page is imported in the target WordPress installation, it might get another ID, 22 for instance. By telling wp-bootstrap that the setting "mypage" in the wp_options table refers to a page, wp-bootstrap will update that option to the new value 22 as part of the process.

### Section: extensions

If the basic functionality in Wp-Bootstrap can't handle content in a certain situation, it's often possible to handle it via an extension. An extension is a PHP class that implements WordPress filters and actions to respond to certain events. For instance, if a plugin uses a custom table, an extension can hook into the 'wp-bootstrap_after_export' action to serialize that table to a file when the site is being exported. During import, the extension would hook into the 'wp-bootstrap_after_import' action to read the serialized file back into the database.

Extensions can be made specifically for a certain plugin or for a specific site project.


| Name                                   | Type   | Description                                                            |
|:---------------------------------------|:-------|:-----------------------------------------------------------------------|
| wp-bootstrap_before_import             | Action | Called before all import activities                                    |
| wp-bootstrap_after_import_settings     | Action | Called after settings have been imported with WP-CFM                   |
| wp-bootstrap_after_import_content      | Action | Called after content (posts, menus etc) have been imported             |
| wp-bootstrap_after_import              | Action | Called after all import activities are done                            |
| wp-bootstrap_before_export             | Action | Called before any export activities starts                             |
| wp-bootstrap_after_export              | Action | Called after all exports activities are done                           |
| wp-bootstrap_option_post_references    | Filter | Lets the extension add names of option values that refer to a post id. |
| wp-wp-bootstrap_option_term_references | Filter | Lets the extension add names of option values that refer to a term id  |

To use an extension, WP Bootstrap must be able to autoload the class. The easiest way to achieve this is to add a PSR4 namespace to the composer.json file of your project:

    {
        "require": {
            "eriktorsner/wp-bootstrap": "~0.4.0"
        },
        "autoload": {
            "psr-4": {
                "MyNamespace\\": "src"
        }
    }

Then place your extension in the sub folder "src" (relative to the project root). Any extension classes will be instantiated at the beginning of the process and a method "init" will be called. In this method the extension can add filters and actions that will be executed in various stages in the installation/import process:

    <?php
    namespace MyNamespace;

    use Wpbootstrap\Container;

    class MyClass
    {
        public function init()
        {
            add_action('wp-bootstrap_after_import', [$this, 'AfterImport']);
        }

        public function AfterImport()
        {
          // do something useful...note that WordPress is loaded in most
          // filters and actions. Go experiment
          $options = get_option('FOOBAR', false);
          ...
        }
      }


A complete appsettings.yml example

    keepDefaultContent: false
    version: 4.4.1
    title: 'Your WordPress site title'

    # section plugins
    plugins:
      # using yaml list with each value on a separate row
      standard:
        - google-analyticator
        - if-menu:0.2.1
        - someplugin:
            version: 3.4
            slug: jetpack
            requires:
              themes: [mychildtheme]
              plugins: [if-menu]
        - woocommerce

      # Defining local plugins using a yaml inline list
      local: [myplugin]

    # section themes
    themes:
      standard: [twentyfourteen]
      local: [mychildtheme]
      active: mychildtheme

    # section settings
    # simple name - value pairs
    settings:
      blogname: 'New title 2'
      blogdescription: 'The next tagline'

    # section content
    # conists of sub sections: posts, menus and taxonomies
    content:
      posts:
        # A separate section for each post type
        # The list contains of post slugs (post_name) to
        # uniquely identify the post. Any type of Yaml list works
        page: [about, members]
        post:
          - hello-world
          - another

        # Instead of specifying a list you can use the
        # asterisk to include all posts of post type 'custom'
        custom: '*'

      # Menus are identified by the menu name and includes
      # a list of locations where this menu should be imported
      # back
      menus:
        main:
          - primary
          - footer

      # Taxonomies are similar to posts.
      # identify the taxonomies to inlcude as well
      # as the individual terms. Optionally, include
      # all terms in a taxonomy using the asterisk
      taxonomies:
        category: '*'
        customcat:
          - This_term
          - That_term

    # The reference section is used during import. It identifies
    # options (in wp_options) that refer to a post or a term.
    # and helps WP Bootstrap maintain data integrity during import
    references:
      # references to post_id's
      posts:
        # references in the options table
        options:
          - some_setting
          - { mysettings: '->term_id' }
          - { mysettings2: '[2]' }
          - { mysettings3: ['->term_id', '->other_term_id']}

       # references to term id's
      terms:
        # references in the options table
        options:
          - some_term
          - { other_term: '->term_id'}

    extensions:
      - MyNamespace\MyClass


## Parent child references and automatic includes

Wp-bootstrap tries it's hardest to preserve references between exported WordPress objects. If you export a page that is the child of another page, the parent page will be included in the exported data regardless if that page was included in the settings. Similar, if you export a menu that points to a page or taxonomy term was not specified, that page and taxonomy term will also be included in the exported data.

### Import matching
When importing into a WordPress installation, wp-bootstrap will use the **slug** to match pages, menus and taxonomy terms. So if the dataset to be imported contains a page with the **slug** 'foobar', that page will be (a) created if it didn't previously exist or (b) updated if it did. The same logic applies to posts (pages, attachments, posts etc), menu items and taxonomy terms.

**Note:** Taxonomies are defined in code rather than in the database. So the exact taxonomies that exist in a WordPress installation are defined at load time. The built in taxonomies are always there, but some taxonomies are defined in a theme or plugin. In order for your taxonomy terms to be imported during the wp-import process, the theme or plugin that defined the taxonomy needs to exist.


## Testing

Since wp-bootstrap relies a lot on WordPress, there's a separate Github repository for testing using Vagrant. The test repo is available at [https://github.com/eriktorsner/wp-bootstrap-test](https://github.com/eriktorsner/wp-bootstrap-test).

## Contributing

Contributions are welcome. Apart from code, the project is in need of better documentation, more test cases, testing with popular themes and plugins and so on. Any type of help is appreciated.

## Change log
[Separate change log](CHANGELOG.md)
