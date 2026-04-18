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
                                                <p style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:21px;letter-spacing:0;color:#333333;font-size:18px;direction: rtl;">
                                                    <strong>🎉 مرحباً بك في عائلة Dreams!</strong></p>
                                                <p style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:21px;letter-spacing:0;color:#666666;font-size:14px;direction: rtl;">
                                                    نحن سعداء جداً بانضمامك إلينا</p>
                                                <br>
                                                <p style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:21px;letter-spacing:0;color:#333333;font-size:14px;direction: rtl;">
                                                    عزيزي/عزيزتي <strong>{{$details['message']['user_name'] ?? 'المستخدم الكريم'}}</strong>،</p>
                                                <br>
                                                <p style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:21px;letter-spacing:0;color:#333333;font-size:14px;direction: rtl;">
                                                    أهلاً وسهلاً بك في منصة <strong>{{$details['message']['site_name'] ?? 'Dreams'}}</strong>! نحن متحمسون لوجودك معنا ونتطلع إلى تقديم أفضل خدمات الرسائل النصية لك.</p>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                
                <!-- Welcome Message Section -->
                <tr>
                    <td align="right"
                        style="Margin:0;padding-top:10px;padding-right:20px;padding-bottom:10px;padding-left:20px;direction: rtl;">
                        <table cellpadding="0" cellspacing="0" width="100%" role="none"
                               style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
                            <tr>
                                <td align="center" valign="top" style="padding:0;Margin:0;width:560px">
                                    <table cellpadding="0" cellspacing="0" width="100%"
                                           style="border:2px solid #5C68E2;border-radius:12px;background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);"
                                           role="presentation">
                                        <tr>
                                            <td align="center" style="Margin:0;padding:30px 20px;direction: rtl;">
                                                <h2 style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:24px;font-weight:bold;line-height:28px;color:#ffffff;direction: rtl;">
                                                    🌟 مرحباً بك في رحلتك معنا! 🌟
                                                </h2>
                                                <br>
                                                <p style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:16px;line-height:24px;color:#ffffff;direction: rtl;">
                                                    أنت الآن جزء من مجتمع Dreams المتميز
                                                </p>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                
                <!-- Features Section -->
                <tr>
                    <td align="right"
                        style="Margin:0;padding-top:20px;padding-right:20px;padding-bottom:10px;padding-left:20px;direction: rtl;">
                        <table cellpadding="0" cellspacing="0" width="100%" role="none"
                               style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
                            <tr>
                                <td align="right" valign="top" style="padding:0;Margin:0;width:560px">
                                    <table cellpadding="0" cellspacing="0" width="100%"
                                           role="presentation"
                                           style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
                                        <tr>
                                            <td align="right" style="padding:0;Margin:0;padding-bottom:15px">
                                                <h3 style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:18px;font-weight:bold;line-height:22px;color:#2c3e50;direction: rtl;">
                                                    🚀 ما يمكنك فعله الآن:
                                                </h3>
                                                <br>
                                                <table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;">
                                                    <tr>
                                                        <td style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;color:#333;text-align:right;direction: rtl;border-bottom:1px solid #f0f0f0;">
                                                            📱 <strong>إرسال الرسائل النصية:</strong> ابدأ في إرسال رسائلك بسهولة وسرعة
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;color:#333;text-align:right;direction: rtl;border-bottom:1px solid #f0f0f0;padding-top:8px;">
                                                            📊 <strong>تتبع الإحصائيات:</strong> راقب أداء رسائلك ومعدلات التسليم
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;color:#333;text-align:right;direction: rtl;border-bottom:1px solid #f0f0f0;padding-top:8px;">
                                                            👥 <strong>إدارة جهات الاتصال:</strong> نظم قوائم جهات الاتصال الخاصة بك
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;color:#333;text-align:right;direction: rtl;border-bottom:1px solid #f0f0f0;padding-top:8px;">
                                                            ⏰ <strong>جدولة الرسائل:</strong> اجدول رسائلك للإرسال في الوقت المناسب
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;color:#333;text-align:right;direction: rtl;padding-top:8px;">
                                                            🎨 <strong>قوالب الرسائل:</strong> استخدم قوالب جاهزة أو أنشئ قوالبك الخاصة
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
                
                <!-- Next Steps Section -->
                <tr>
                    <td align="right"
                        style="padding:20px 20px 0 20px;direction: rtl;">
                        <table cellpadding="0" cellspacing="0" width="100%" role="none"
                               style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
                            <tr>
                                <td align="right" valign="top" style="padding:0;Margin:0;width:560px">
                                    <table width="100%" role="presentation" cellpadding="0"
                                           cellspacing="0"
                                           style="border-collapse:collapse;border-spacing:0px;border:1px solid #e0e0e0;border-radius:8px;background-color:#f8f9fa;">
                                        <tr>
                                            <td align="right" style="padding:20px;Margin:0;direction: rtl;">
                                                <h3 style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:16px;font-weight:bold;line-height:20px;color:#2c3e50;direction: rtl;">
                                                    📋 الخطوات التالية:
                                                </h3>
                                                <br>
                                                <ol style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;line-height:21px;color:#333333;text-align:right;direction: rtl;margin:0;padding-right:20px;">
                                                    <li style="margin-bottom:8px;">قم بتسجيل الدخول إلى حسابك</li>
                                                    <li style="margin-bottom:8px;">استكشف لوحة التحكم الخاصة بك</li>
                                                    <li style="margin-bottom:8px;">أضف جهات الاتصال الأولى</li>
                                                    <li style="margin-bottom:8px;">أرسل رسالتك التجريبية الأولى</li>
                                                    <li style="margin-bottom:8px;">تواصل معنا إذا كنت بحاجة لأي مساعدة</li>
                                                </ol>
                                                <br>
                                                <p style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;line-height:21px;color:#666666;text-align:right;direction: rtl;">
                                                    💡 <strong>نصيحة:</strong> ابدأ بإرسال رسالة تجريبية لنفسك للتأكد من أن كل شيء يعمل بشكل مثالي!</p>
                                                <br>
                                                <p style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;line-height:21px;color:#333333;text-align:right;direction: rtl;">
                                                    نحن هنا لدعمك في كل خطوة. مرحباً بك مرة أخرى في <strong>{{$details['message']['site_name'] ?? 'Dreams'}}</strong>!</p>
                                                <br>
                                                <p style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:12px;line-height:18px;color:#999999;text-align:right;direction: rtl;">
                                                    تم إرسال هذه الرسالة تلقائياً من نظام Dreams. لا تحتاج للرد على هذه الرسالة.</p>
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
