=== Product Catalog Simple ===
Contributors: impleCode, ndre
Tags: product catalog, product catalogue, catalog, catalogue, product, implecode, manage, plugin, display products, list products, items, products, wp catalog, blog catalog, product feed, product management, product portfolio, portfolio, directory, gallery, product gallery, widget, post, plugin, admin, posts, inventory, restaurant menu, responsive, wp catalogue, catalog of goods, collection, catalogs, digital goods, list, listing, products catalog, wp catalog, custom post type, custom post types
Requires at least: 3.7
Tested up to: 6.8
Stable tag: 1.8.1
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Catalog plugin with fully customizable responsive design, search and categories. Best for product catalog and services or portfolio presentation.

== Description ==

Product Catalog Simple is a free WordPress plugin that helps you to manage and display your X catalog. Replace the X with whatever you need to catalog e.g. flowers, houses, boats, mixed products, services, portfolio. 

__[Live Demo](http://demo.implecode.com?cam=wordpress-org-catalog-page&key=x-demo-link)__

= Built with WordPress =

Product Catalog Simple is build with WordPress functions to provide maximum compatibility with other plugins. Custom post types and custom taxonomies are used for items and categories.

= Total output control =
The admin has complete control on the plugin output with fully customisable templates and dozens of shortcodes.

The catalog items can be categorized with hierarchical categories.

Product Catalog Simple as full of WordPress hooks is extremely customizable and extensible.

= User Friendly Design =

Product Catalog Simple has three different, fully responsive product listing styles. The individual item page is simple but complete with 100% responsive design.

= Modularity Philosophy =

You will never need to do any workaround to disable not needed functionality. Product Catalog Simple is built to be extended.

= Customizable dashboard =

Product Catalog Simple lets you name your catalog to give the admin a personalized feeling.

= Display Items with a Shortcode =

Use the shortcode to show items whenever needed on the website. Just paste on any page: [show_products] and watch all items in place of the shortcode.

To show items from just one category, use: [show_products category="2"] where 2 is category ID (it's possible to display several categories by inserting comma separated IDs). 

To display items by IDs, use: [show_products product="5"], where 5 is item ID.

= Display Categories with a Shortcode =

You can use [show_categories] shortcode to show the item categories on home page or any other WordPress page.

= Smart Widgets =

Product Catalog Simple comes with two widgets: 

__1. Item Categories__ - show all item categories in CSS customisable list. The widget has also several customisation settings.

__2. Item Search__ - generates a search form that allows to search the items in the catalog.

= Product Catalog Simple Extensions =

This product catalog plugin is extensible. The feature packed extensions is that what makes Product Catalog Simple plugin a more specialized solution. [Browse the extensions](https://implecode.com/wordpress/plugins/?cam=wordpress-org-catalog-page&key=x-extensions-link#extensions "Product Catalog Simple extensions on implecode.com").

Some extensions examples:

__[Product Reviews](https://wordpress.org/plugins/reviews-plus/)__ - activate rich reviews for your catalog products.

__[Price Field](https://wordpress.org/plugins/price-field/)__ - activate price for your catalog products.

__[Attributes Table](https://wordpress.org/plugins/attributes-table/)__ - activate product attributes tab for your catalog products.

__[Shipping Options](https://wordpress.org/plugins/shipping-options/)__ - activate shipping for your catalog products.

__and many more extensions already available and in constant development. You will never be limited!__

== Frequently Asked Questions ==

= What is the difference between Product Catalog Simple and eCommerce Product Catalog? =

Product Catalog Simple is a simple and extensible base on which you can built your deeply customized product catalog, services list, portfolio etc. It has only basic features. While developing eCommerce Product Catalog we realized that many users are trying to disable some functionality because they don't need it. That's why we came up with Product Catalog Simple which can be extended but it doesn't force any functionality.

Product Catalog Simple and eCommerce Product Catalog share the same base so you can switch between them without loosing configuration, products and categories.

== Screenshots ==

The screenshots below show the example usage of Product Catalog Simple as a product catalog:

1. Product Catalog Simple main listing with categories and items enabled. The "Modern Grid" listing design is used here.
2. Product Catalog Simple main listing with categories and items enabled. The "Classic List" listing design is used here.
3. Product Catalog Simple main listing with categories and items enabled. The "Classic Grid" listing design is used here.
4. Simple product page with additional images in description
5. Main catalog settings screen
6. Categories, SEO and breadcrumbs settings


== Installation ==

= Minimum Requirements =

* WordPress 3.5 or greater
* PHP version 5.2.4 or greater
* MySQL version 5.0 or greater

= Automatic installation =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don’t even need to leave your web browser. To do an automatic install of impleCode Product Catalog Simple, log in to your WordPress admin panel, navigate to the Plugins menu and click Add New.

In the search field type “Product Catalog Simple” and click Search Plugins. Once you’ve found Product Catalog Simple plugin you can view details about it such as the the rating and description. Most importantly of course, you can install it by simply clicking Install Now. After clicking that link you will be asked if you’re sure you want to install the plugin. Click yes and WordPress will automatically complete the installation.

= Manual installation =

The manual installation method involves downloading our item catalog plugin and uploading it to your web-server via your favourite FTP application.

1. Download the plugin file to your computer and unzip it
2. Using an FTP program, or your hosting control panel, upload the unzipped plugin folder to your WordPress installation’s wp-content/plugins/ directory.
3. Activate the plugin from the Plugins menu within the WordPress admin.

= Upgrading =

Automatic updates should work like a charm; as always though, ensure you backup your site just in case.

If on the off-chance you do encounter issues with the plugin pages after an update you simply need to flush the permalinks by going to WordPress > Settings > Permalinks and hitting 'save'. That should return things to normal.

== Changelog ==

= 1.8.1 – 17/04/2025  =

* Improved filtering performance
* Fixed wp session incompatibility
* WordPress compatibility update

= 1.8.0 – 25/02/2025  =

* New block to display product page
* New block to display category page
* Improved comptatibility with block themes
* Fix plain product page template for some themes
* Security: properly escape all show_products block parameters

= 1.7.11 – 10/12/2024  =

* Compatibility update

= 1.7.10 – 15/11/2024  =

* Fix PHP notice that shows up on the product page with WP_DEBUG active

= 1.7.9 – 13/11/2024  =

* WordPress 6.7 compatibility update
* Fix PHP notices for older WordPress versions
* Fix show products shortcode pagination on main catalog page
* Fix PHP depreciated errors for some configurations
* Category description shortcode handling
* Avoid PHP errors within the category filter under certain exceptional circumstances

= 1.7.8 – 09/04/2024  =

* Added container for the featured products
* Category filter styling improved when used outside of the catalog context
* Added WooCommerce menu in settings for additional integration options
* Ability to add catalog icons to the site editor navigation
* Fix category select dropdown on filtered pages
* Fix the modern grid color picker
* Fix the category page product count for some themes
* Security – added nonce check to admin notifications hiding feature
* WordPress 6.5 compatibility update

= 1.7.7 – 14/11/2023  =

* Fix categories metabox to show the correct hierarchy

= 1.7.6 – 08/11/2023  =

* Better compatibility with the classic editor plugin
* PHP 8.2 compatibility update
* Security fix
* WordPress 6.4 compatibility update

= 1.7.5 – 21/08/2023  =

* Fix the catalog category dropdown

= 1.7.4 – 14/08/2023  =

* WordPress compatibility update
* PHP 8.2 compatibility update

= 1.7.3 – 02/08/2023  =

* Polylang compatibility update
* WPML compatibility update

= 1.7.2 – 04/07/2023  =

* Fix the attributes table compatibility issue

= 1.7.1 – 27/06/2023  =

* Improved shortcode filtering
* Mobile product page tabs improved
* Settings reset button added to the general settings screen

= 1.7.0 – 04/04/2023  =

* Filters performance improvements
* Welcome screen design improvement
* Security update
* Multiple WordPress blocks added to display the catalog parts
* New product gallery block to display a product image
* New short description block
* New product name block
* New block styling options
* Now the plain template is the default one if blocks editor is enabled for products
* New line character changed in files
* WordPress 6.2 compatibility update

= 1.6.17 – 09/11/2022  =

* Compatibility update

= 1.6.16 – 11/10/2022  =

* Security update

= 1.6.15 – 08/10/2022  =

* Security update
* Theme integration improvements
* Support for filtering multiple shortcodes on one page using the filters bar
* Session handling improvements

= 1.6.14 – 16/08/2022  =

* Fix the per_row parameter for the show_categories shortcode

= 1.6.13 – 02/08/2022  =

* Fix the upgrade bug that shows up for some configurations

= 1.6.12 – 01/08/2022  =

* Improved product block editor
* Mutlilingual support improvements
* Improved filters handling
* Improved performance on the front-end and in the admin side

= 1.6.11 – 25/05/2022  =

* A new [catalog_filters_bar] shortcode to show the catalog filters bar widget area
* Improved ajax filters handling
* Elementor compatibility update
* SiteGround CSS and JS optimization compatibility
* Session handling improvements
* Magnifier and lightbox position fixed when body width is applied
* WordPress 6.0 compatibility update

= 1.6.10 – 17/02/2022  =

* The query for uncategorized products fixed
* Fix page title on search results page for some themes
* Improved default product image assignment

= 1.6.9 – 29/01/2022  =

* A new option to display only main categories without loose products on main listing page
* Performance improvements on search and filters
* Added div container to wrap all catalog content
* Fix product duplication issue on some configurations
* Display more items in related categories section
* Ability to filter private products when logged in with the correct capabilities
* Compatibility with WordPress 5.9

= 1.6.8 – 18/10/2021  =

* Compatibility update

= 1.6.7 – 12/10/2021  =

* Fix a rare outdated PHP error

= 1.6.6 – 20/09/2021  =

* Fix catalog blocks on widgets screen
* Improved multiligual support
* Improved optimization for mobile
* Divi Builder compatibility improved

= 1.6.5 – 27/07/2021  =

* Fix missing data on individual product page for some configurations

= 1.6.4 – 23/07/2021  =

* Improved Elementor support

= 1.6.3 – 21/07/2021  =

* WordPress 5.8 compatibility update
* Set duplicated product as draft
* Ability to enable Gutenberg editor for product description
* Improved support for non latin characters in catalog slugs
* Theme breadcrumbs compatibility improvements
* Product search and product categories blocks added
* Fix breadcrumbs data attributes
* Fix template issues with structured data enabled
* Fix the category description HTML

= 1.6.2 – 22/12/2020  =

* Additional WordPress 5.6 compatibility update

= 1.6.1 – 15/12/2020  =

* WordPress 5.6 compatibility update

= 1.6.0 – 26/11/2020  =

* New initial configuration tutorial and screen
* Navigation bar added on category edit and product edit screens
* Category filter fixed on product search
* improved theme integration to support more themes
* Catalog Multilingual compatibility improvements
* Minor Product Catalog styling adjustments for better theme compatibility
* Fix Product Catalog pagination on some configurations
* Better Elementor support
* Ability to force the category page header display
* Product filters fix for certain configurations
* Outdated PHP compatibility errors fixed

= 1.5.13 – 25/08/2020  =

* Security - fixed nonce check

= 1.5.12 – 13/07/2020  =

* Fix the default image showing main product catalog page featured image
* Multiple Product Catalog performance improvements
* Elementor compatibility improvement
* Optimization for even better performance
* Additional CSS and JS  performance improvements

= 1.5.11 – 06/04/2020  =

* Fix empty catalog output in admin side block editor
* WordPress compatibility update

= 1.5.10 – 19/03/2020  =

* Fix product category filter count

= 1.5.9 – 17/03/2020  =

* Theme compatibility improvement

= 1.5.8 – 13/03/2020  =

* Fix product import when the price module is disabled

= 1.5.7 – 13/03/2020  =

* Display catalog configuration wizard only on plugins screen and product catalog admin screens
* Fix the image on product catalog listing for some configurations
* Notifications adjustment
* Fix empty product catalog pages for some configurations
* Correct product filters values when using shortcode
* New catalog layout integration options to manage template, product name display, breadcrumbs position and other options

= 1.5.6 – 24/02/2020  =

* Multiple Product Catalog performance improvements
* Compatibility with older WordPress versions (up to 3.7)
* Remove the unnecessary no products available message in some configurations

= 1.5.5 - 24/01/2020 =

* Import without price module
* The option: Category Page shows > subcategories will display products on category page only if the product is directly assigned to the currently viewed category

= 1.5.4 - 05/12/2019 =

* Multiple Product Product Catalog Simple performance improvements
* Minor responsive styling improvements
* Theme integration improvements
* Product import and export link added on products admin screen

= 1.5.3 - 23/10/2019 =

* Empty category pages fixed when using show catalog block

= 1.5.2 - 17/10/2019 =

* Product Catalog gets 2 new blocks
* New block to display main Product Catalog page content
* New block to display product categories
* Ability to select product subcategories in show products block
* Translation update
* Product image uploader usability improvements
* Product Catalog error reporting improved
* Product catalog category filter full support for product shortcodes
* Product Catalog activation error fix
* Structured data template fix
* Structured data feature improvements
* Product catalog theme integration improvements in simple mode

= 1.5.1 - 26/07/2019 =

* category pages support added for product catalog simple mode
* product catalog icons in main menu or on the top of the screen on mobile – icons for catalog and search – the icons are optional and customizable – catalog icons can be enabled in Catalog Settings > Catalog Design > Design Schemes or inside theme customizer
* individual product pages structured data is now optional
* structured data is fully customizable with the template file from templates/template-parts/product-page/structured-data.php
 

= 1.5.0 - 17/05/2019 =

* Ability to assign featured products and show them with [show_featured_product] shortcode
* Product Catalog icons in main menu or on the top of the screen on mobile – icons for catalog and search – the icons are optional and customizable – Product Catalog icons can be enabled in Catalog Settings > Catalog Design > Design Schemes or inside theme customizer
* Product Catalog now has support for category pages in theme simple mode
* Twenty Nineteen theme native support
* Better support for shortcode mode on home page
* Classic list description on category listing fixed
* Product Filters Bar filters usability improvement for small screens
* Responsive pagination usability improvement
* Responsive filters performance and usability improvements

= 1.4.3 - 17/12/2018 =

* New block to display catalog products
* WordPress 5.0 compatibility update (nothing changes in products editing)
* Fix the error when the main listing is disabled in shortcode mode

= 1.4.2 - 30/10/2018 =

* Product catalog general settings screen markup improved for some configurations

= 1.4.1 - 28/09/2018 =

* Price styling fixed on product listing

= 1.4.0 - 12/09/2018 =
 
* Feature - optional magnifier on main product image - when you point the mouse cursor over the main product image on product catalog individual product page it will show the magnified portion of the image on the left or right side depending of the product page template enabled
* The currency switcher in the catalog settings and the configuration wizard now shows the currency code first for better usability
* Ability to edit each individual product catalog listing element, also classic grid, modern grid and classic list with template files placed in child theme folder
* Database and disk usage performance improved when displaying catalog pages
* Product Catalog privacy info added
* Now Product Catalog doesn't use any cookies if the Quote Cart and Shopping Cart are disabled
* Now the catalog configuration wizard will reset on activation only if there are no products added
* [show_products] shortcode now by default shows up to 100 products to avoid server timeout errors, this can be changed with product_limit shortcode parameter
* Admin usability and styling improvements - configuration tips added
* Ability to display main product catalog listing anywhere on the website with a shortcode without changing the catalog parent slug
* Display the no products available message when the product list is empty
* New theme integration mode that will use theme default design for listing pages
* Better usability for integration settings

= 1.3.5 - 16/03/2018 =

* Tweak – template file for shortcode integration container
* Tweak – shortcode product catalog compatibility with more themes

= 1.3.4 - 09/02/2018 =

* Tweak – startup tutorial added
* Tweak – show admin notices only on catalog admin pages
* Tweak – better page builders support for product catalog pages

= 1.3.3 - 23/01/2018 =

* Tweak - product catalog multilingual support improved
* Tweak - support for categories import from other post type
* Tweak - compatibility info modified
* Tweak - support for multiple product categories import
* Tweak - shortcode integration improved
* Tweak - code improvements for even better performance and readability
* Fix - search pagination fixed

= 1.3.2 - 07.12.2017 =

* Tweak – compatibility with WordPress password protected feature
* Tweak – admin edit links on product page when logged in as admin
* Tweak – new shortcode theme integration method for Product Product Catalog Simple
* Tweak – multiple code improvements for even better performance and readability

= 1.3.1 - 12.06.2017 =

* Fix - admin stylesheet works again

= 1.3.0 - 12.06.2017 =

* Feature – ability to customize product page and product display in WordPress customizer
* Feature – ability to import products from other post type to Product Product Catalog Simple
* Tweak – settings usability improved
* Tweak – initial configuration improved
* Tweak – simple mode improved
* Tweak – ajax filters usability improvements for all eCommerce pages

= 1.2.6 - 02.05.2017 =

* Fix – per row attribute
* Security – WP Sessions Manager updated

= 1.2.5 - 24.04.2017 =

* Tweak – breadcrumbs schema.org
* Fix – remove unnecessary CSS rules
* Fix – theme integration width

= 1.2.4 - 24.03.2017 =

* Tweak - Code optimisation for even better performance
* Tweak - admin responsiveness improved
* Fix - theme integration width
* Fix - ajax pagination fixed for certain configurations
* Fix - ajax shortcode search URL fixed

= 1.2.3 - 28.02.2017 =

* Fix - uninstallation error fixed
* Fix - product import / export screen fixed

= 1.2.2 - 28.02.2017 =

* Tweak - ajax filters for shortcodes improved

= 1.2.1 - 09.02.2017 =

* Fix - draft items visibility fixed on filters ajax requests

= 1.2.0 - 07.02.2017 =

* Tweak - filters and pagination usability improved. Now it works without page reload. This can be disabled with the following code added to the theme functions.php:

= 1.1.6 - 03.01.2017 =

* Compatibility update

= 1.1.5 - 12.12.2016 =

* Compatibility with WordPress 4.7

= 1.1.4 - 28.10.2016 =

* Fix - extensions menu stying fixed

= 1.1.3 - 27.10.2016 =

* Fix - compatibility with PHP < 5.5 restored

= 1.1.2 - 26.10.2016 =

* Fix - permissions to see settings fixed

= 1.1.1 - 11.10.2016 =

* Tweak - compatibility with free Reviews Plus extension

= 1.1.0 - 08.09.2016 =

* Feature - new single page template can be selected in Catalog > Settings > Catalog Design > Single Page Design
* Multiple tweaks and fixes for better stability, usability and performance

= 1.0.4 - 30.11.2015 =

* Fix - Error when searching fixed

= 1.0.3 - 26.11.2015 =

* Fix - permission errors fixed on settings page

= 1.0.2 - 15.10.2015 =

* Tweak - extensions menu added
* Tweak - support for free and premium addons

= 1.0.1 - 09.10.2015 =

* Fix - javascript error on item save

= 1.0.0 =

* Initial Release

== Upgrade Notice ==

* Fixes & Tweaks