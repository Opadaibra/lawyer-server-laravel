<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $otp;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($otp)
    {
        $this->otp = $otp;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('رمز استعادة كلمة المرور')
                    ->html('
                        <div style="font-family: Arial, sans-serif; padding: 20px;">
                            <h2>استعادة كلمة المرور</h2>
                            <p>لقد طلبت استعادة كلمة المرور الخاصة بحسابك.</p>
                            <p>رمز التحقق (OTP) الخاص بك هو:</p>
                            <h1 style="color: #4A90E2; letter-spacing: 5px;">' . $this->otp . '</h1>
                            <p>هذا الرمز صالح لمدة 15 دقيقة فقط.</p>
                            <p>إذا لم تطلب استعادة كلمة المرور، يرجى تجاهل هذا البريد.</p>
                        </div>
                    ');
    }
}
