[![Current version](https://img.shields.io/packagist/v/maatify/mailer)](https://packagist.org/packages/maatify/mailer)
[![Packagist PHP Version Support](https://img.shields.io/packagist/php-v/maatify/mailer)](https://packagist.org/packages/mailer/logger)
[![Monthly Downloads](https://img.shields.io/packagist/dm/maatify/mailer)](https://packagist.org/packages/maatify/logger/mailer)
[![Total Downloads](https://img.shields.io/packagist/dt/maatify/mailer)](https://packagist.org/packages/maatify/logger/mailer)


# Mailer
Official PHP library for maatify.dev Mailer handler, known by our team

## Installation

    composer require maatify/mailer
    
Don't forget to create Class App\Assist\Maile

    namespace App\Assist\Mailer;

    class StgMail
    {
        public  const stg_mailer_url = 'YOUR STG SENDER';
    }
    
