## DClassifieds v3 Laravel 5.2 Version

[![Donate via PayPal](https://img.shields.io/badge/donate-paypal-87ceeb.svg)](https://www.paypal.com/cgi-bin/webscr?cmd=_donations&currency_code=EUR&business=paypal@dedo.bg&item_name=donation%20for%20DClassifieds%20project)
*Please consider supporting this project by making a donation via [PayPal](https://www.paypal.com/cgi-bin/webscr?cmd=_donations&currency_code=EUR&business=paypal@dedo.bg&item_name=donation%20for%20DClassifieds%20project)*

DClassifieds Free Classifieds Script is free open source classifieds script based on Laravel 5.2 framework. For this reason DClassifieds inherits all benefits of the Laravel framework futures.

### License

DClassifieds Free Classifieds Script is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)

### Demo

[http://v3.dclassifieds.eu/](http://v3.dclassifieds.eu/)

### Features
- Unlimited Levels of Categories
- Unlimited Levels of Locations
- 7 Types of categories (different fields in ad publish and ad search)
- Lightning fast, several types of caching
- Laravel based
- Seo Ready
- Banner Management System
- Magic Keywords
- Social Login (Facebook, Twitter, Google)
- User Messaging system
- User Wallet / Promo ads
- 4 Payment Gateways (Mobio SMS, Fortumo SMS, Paypal Standard, Stripe)
- Bonus/Reward System
- Favorite Ads
- Static pages
- Themes support
- and many more

### Server Requirements

- PHP >= 5.5.9
- OpenSSL PHP Extension
- PDO PHP Extension
- Mbstring PHP Extension
- Tokenizer PHP Extension

### Install
1. Download the archive
2. Extract
3. Rename .env.example to .env
4. Fill this fields in .env file :
    - DB_HOST=your db host
    - DB_DATABASE=your database name
    - DB_USERNAME=your database user name
    - DB_PASSWORD=your db password
5. Import the dclassifieds.sql to your database name (see 4.)
6. Upload the script to your hosting only the content of "public" folder must be in your document root, all other files must be outside
7. Create new user with your mail and password
8. Login with user: admin@admin.com pass: 123456, goto admin -> user, active the new user and make it admin, then deactivate/delete admin@admin.com user
9. Goto to admin panel settings, setup your configuration
    - Set "Encryption Key" to custom string 32 characters length
    - Set reCaptcha
    - Set Facebook Login
    - Change Cron password
    - etc.
10. Add rss to your google webmasters tools, rss adress : http://your domein/rss
    - set number of ads in rss from settings
11. Set cron job to deactivate expired ads: http://your domain/deactivate/cron password from admin
    - once a day
12. Set cron job to send warning mail for expiring soon ads: http://your domain/sendmaildeactivatesoon/cron password from admin
    - depends on ads volume and hosting limits, set it to several times a day, set number of mails from settings
13. Set cron job to send warning mail for expiring soon promo ads: http://your domain/sendmailpromoexpiresoon/cron password from admin
    - depends on ads volume and hosting limits, set it to several times a day, set number of mails from settings
14. Set cron job to remove promo from expired promo ads: http://your domain/deactivatepromo/cron password from admin
    - once a day
15. Set cron job to remove double ads (with duplicate content): http://your domain/removedouble/cron password from admin
    - several times a day
16. Make sure all sub folders in /public/uf are writable (if needed change mode to 777)
17. Make sure all sub folders in /storage are writable (if needed change mode to 777)
18. Enjoy :)
19. If you like the script please donate

### Info
If you find bugs, please report here: https://github.com/gdinko/dclassifieds.laravel/issues

### Services
- Installation Services - please contact
- Custom Development Services - please contact

### Contact and Credits
- Made in Bulgaria, Sofia
- Developer: Dinko Georgiev - contact@dclassifieds.eu
- QA: Georgi Georgiev
- p.s. we are not relatives :)

### How to translate
Copy the folder resources/lang/en, then rename it and translate, then change the language from admin -> settings

### How to make your own theme
1. Copy the basic theme from resources/views/themes/basic, name it like you want
2. Change the theme from admin -> settings
3. Copy your css/js files to public/css and public/js

### How to make your own functionality
1. Place your frontend controllers in app/Http/Controllers
2. Place your frontend routing files in app/Http/Routes/frontend
3. Place your backend controllers in app/Http/Controllers/Admin
4. Place your backend routing files in app/Http/Routes/backend
5. For more info read Laravel 5.2 Documentation