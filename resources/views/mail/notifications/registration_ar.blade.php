@extends('layouts.base')

@section('content')

<table cellpadding="0" cellspacing="0" align="center" class="es-content" role="none"
       style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;width:100%;table-layout:fixed !important">
    <tr>
        <td align="center" style="padding:0;Margin:0">
            <table bgcolor="#ffffff" align="center" cellpadding="0" cellspacing="0"
                   class="es-content-body" role="none"
                   style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;background-color:#FFFFFF;width:600px">
                <tr>
                    <td align="right"
                        style="Margin:0;padding-right:20px;padding-bottom:10px;padding-left:20px;padding-top:20px;direction: rtl;">
                        <table cellpadding="0" cellspacing="0" width="100%" role="none"
                               style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
                            <tr>
                                <td align="right" valign="top" style="padding:0;Margin:0;width:560px">
                                    <table cellpadding="0" cellspacing="0" width="100%"
                                           role="presentation"
                                           style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
                                        <tr>
                                            <td align="right"
                                                style="padding:0;Margin:0;padding-top:5px;padding-bottom:5px">
                                                <p style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:21px;letter-spacing:0;color:#333333;font-size:14px;direction: rtl;">
                                                    <strong>مرحبًا بك في فريق الاحلام!</strong></p>
                                                <p style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:21px;letter-spacing:0;color:#333333;font-size:14px;direction: rtl;">
                                                    نحن سعداء بانضمامك إلى مجتمعنا.</p>
                                                <br>
                                                <p style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:21px;letter-spacing:0;color:#333333;font-size:14px;direction: rtl;">
                                                    لإكمال عملية التسجيل , يرجى استخدام رمز التحقق (OTP) الموضح أدناه. هذا الرمز ضروري للتحقق من حسابك وضمان أمان معلوماتك.</p>
                                                <br>
                                                <p style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:21px;letter-spacing:0;color:#333333;font-size:14px;direction: rtl;">
                                                    شكرًا لاختيارك <strong>فريق الاحلام</strong> كمزود خدمة الرسائل الموثوق به. نحن هنا لدعمك في كل خطوة.</p>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td align="right"
                        style="Margin:0;padding-top:10px;padding-right:20px;padding-bottom:10px;padding-left:20px;direction: rtl;">
                        <table cellpadding="0" cellspacing="0" width="100%" role="none"
                               style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
                            <tr>
                                <td align="center" valign="top" style="padding:0;Margin:0;width:560px">
                                    <table cellpadding="0" cellspacing="0" width="100%"
                                           style="border:2px dashed #cccccc;border-radius:5px"
                                           role="presentation">
                                        <tr>
                                            <td align="center"
                                                style="Margin:0;padding:20px 20px 10px 20px">
                                                <h1 style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:46px;font-weight:bold;line-height:55.2px;color:#5C68E2;direction: rtl;">
                                                    <strong>{{$details['message']['otp_code']}}</strong>
                                                </h1>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td align="right"
                        style="padding:20px 20px 0 20px;direction: rtl;">
                        <table cellpadding="0" cellspacing="0" width="100%" role="none"
                               style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
                            <tr>
                                <td align="right" valign="top" style="padding:0;Margin:0;width:560px">
                                    <table width="100%" role="presentation" cellpadding="0"
                                           cellspacing="0"
                                           style="border-collapse:collapse;border-spacing:0px">
                                        <tr>
                                            <td align="right" style="padding:0;Margin:0;padding-bottom:15px">
                                                <p style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;line-height:21px;color:#333333;text-align:right;direction: rtl;">
                                                    <strong>أمانك هو أولويتنا القصوى.</strong></p>
                                                <br>
                                                <p style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;line-height:21px;color:#333333;text-align:right;direction: rtl;">
                                                    إذا لم تقم بطلب هذا الرمز من <strong>فريق الاحلام</strong>، يرجى تجاهل هذه الرسالة. نحن نأخذ محاولات الدخول غير المصرح بها على محمل الجد ونوصي دائمًا بالحذر من أي تواصل غير متوقع.</p>
                                                <br>
                                                <p style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;line-height:21px;color:#333333;text-align:right;direction: rtl;">
                                                    إذا كانت لديك أي مخاوف أو لاحظت نشاطًا غير معتاد، لا تتردد في التواصل مع فريق الدعم لدينا. نحن هنا لضمان أن تجربتك مع <strong>فريق الاحلام</strong> آمنة ومحمية.</p>
                                                <br>
                                                <p style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;line-height:21px;color:#333333;text-align:right;direction: rtl;">
                                                    نشكرك على اهتمامك وتعاونك في الحفاظ على أمان خدماتنا.</p>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

@endsection
