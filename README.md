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

#### EMAIL_SITE_URL  // => ending with slash
#### EMAIL_SITE_LOGO
#### EMAIL_SITE_NAME
#### SITE_URL // => no slash at the end

### Don't forget to create Class App\Assist\Config
```php
namespace App\Assist\Config;

class MailerConfig
{
    private static self $instance;

    public static function obj(string $language_short_code = ''): self
    {
        if (empty(self::$instance)) {
            self::$instance = new self($language_short_code);
        }

        return self::$instance;
    }

    public function __construct(string $language_short_code = '')
    {
        if (empty($language_short_code)) {
            $language_short_code = 'en';
        }

        if(in_array($language_short_code, ['en', 'ar'])){
            $this->language_short_code = $language_short_code;
        }
    }

    private string $language_short_code = 'en';

    public function subjectTempPass(): string
    {
        return match ($this->language_short_code) {
            'ar' => 'الرقم السري المؤقت الخاص بك',
            default => 'Your Temporary Password',
        };
    }

    public function subjectResetPass(): string
    {
        return match ($this->language_short_code) {
            'ar' => 'تغيير كلمة المرور',
            default => 'Reset Password',
        };
    }

    public function subjectConfirmMail(): string
    {
        return match ($this->language_short_code) {
            'ar' => 'تأكيد البريد الإلكتروني',
            default => 'Confirm Mail',
        };
    }

    public function subjectOTPCode(): string
    {
        return match ($this->language_short_code) {
            'ar' => 'الرقم السري المتغير',
            default => 'OTP Code',
        };
    }

    public function subjectConfirmCode(): string
    {
        return match ($this->language_short_code) {
            'ar' => 'رمز التحقق',
            default => 'Confirm Code',
        };
    }
}
```
