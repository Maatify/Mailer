[![Current version](https://img.shields.io/packagist/v/maatify/mailer)][pkg]
[![Packagist PHP Version Support](https://img.shields.io/packagist/php-v/maatify/mailer)][pkg]
[![Monthly Downloads](https://img.shields.io/packagist/dm/maatify/mailer)][pkg-stats]
[![Total Downloads](https://img.shields.io/packagist/dt/maatify/mailer)][pkg-stats]
[![Stars](https://img.shields.io/packagist/stars/maatify/mailer)](https://github.com/maatify/Mailer/stargazers)

[pkg]: <https://packagist.org/packages/maatify/mailer>
[pkg-stats]: <https://packagist.org/packages/maatify/mailer/stats>
# Mailer
Official PHP library for maatify.dev Mailer handler, known by our team

## Installation

    composer require maatify/mailer
    
### Don't forget to create Class App\Assist\Maile

```php
namespace App\Assist\Mailer;

class StgMail
{
    public  const stg_mailer_url = 'YOUR STG SENDER';
}
```

    
### Create Env 

EMAIL_SITE_URL  // => ending with slash
EMAIL_SITE_LOGO
EMAIL_SITE_NAME
SITE_URL // => no slash at the end