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
                                                <p style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:21px;letter-spacing:0;color:#333333;font-size:16px;direction: rtl;">
                                                    <strong>🔔 إشعار إداري - مستخدم جديد</strong></p>
                                                <p style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:21px;letter-spacing:0;color:#666666;font-size:14px;direction: rtl;">
                                                    تم تسجيل مستخدم جديد في النظام</p>
                                                <br>
                                                <p style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:21px;letter-spacing:0;color:#333333;font-size:14px;direction: rtl;">
                                                    مرحباً، تم تسجيل مستخدم جديد في منصة <strong>Dreams SMS</strong>. يرجى مراجعة التفاصيل أدناه:</p>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                
                <!-- User Details Section -->
                <tr>
                    <td align="right"
                        style="Margin:0;padding-top:10px;padding-right:20px;padding-bottom:10px;padding-left:20px;direction: rtl;">
                        <table cellpadding="0" cellspacing="0" width="100%" role="none"
                               style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
                            <tr>
                                <td align="center" valign="top" style="padding:0;Margin:0;width:560px">
                                    <table cellpadding="0" cellspacing="0" width="100%"
                                           style="border:1px solid #e0e0e0;border-radius:8px;background-color:#f9f9f9"
                                           role="presentation">
                                        <tr>
                                            <td align="right" style="Margin:0;padding:20px;direction: rtl;">
                                                <h3 style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:18px;font-weight:bold;line-height:22px;color:#2c3e50;direction: rtl;">
                                                    📋 تفاصيل المستخدم الجديد
                                                </h3>
                                                <br>
                                                <table width="100%" cellpadding="5" cellspacing="0" style="border-collapse:collapse;">
                                                    <tr>
                                                        <td style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;color:#555;font-weight:bold;text-align:right;direction: rtl;width:30%;">
                                                            👤 الاسم:
                                                        </td>
                                                        <td style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;color:#333;text-align:right;direction: rtl;">
                                                            {{$details['message']['user_name'] ?? 'غير محدد'}}
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;color:#555;font-weight:bold;text-align:right;direction: rtl;padding-top:8px;">
                                                            🏷️ اسم المستخدم:
                                                        </td>
                                                        <td style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;color:#333;text-align:right;direction: rtl;padding-top:8px;">
                                                            {{$details['message']['username'] ?? 'غير محدد'}}
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;color:#555;font-weight:bold;text-align:right;direction: rtl;padding-top:8px;">
                                                            📧 البريد الإلكتروني:
                                                        </td>
                                                        <td style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;color:#333;text-align:right;direction: rtl;padding-top:8px;">
                                                            {{$details['message']['email'] ?? 'غير محدد'}}
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;color:#555;font-weight:bold;text-align:right;direction: rtl;padding-top:8px;">
                                                            📱 رقم الهاتف:
                                                        </td>
                                                        <td style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;color:#333;text-align:right;direction: rtl;padding-top:8px;">
                                                            {{$details['message']['number'] ?? 'غير محدد'}}
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;color:#555;font-weight:bold;text-align:right;direction: rtl;padding-top:8px;">
                                                            🕐 وقت التسجيل:
                                                        </td>
                                                        <td style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;color:#333;text-align:right;direction: rtl;padding-top:8px;">
                                                            {{$details['message']['registration_time'] ?? 'غير محدد'}}
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;color:#555;font-weight:bold;text-align:right;direction: rtl;padding-top:8px;">
                                                            🌐 الموقع:
                                                        </td>
                                                        <td style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;color:#333;text-align:right;direction: rtl;padding-top:8px;">
                                                            {{$details['message']['site_name'] ?? 'Dreams SMS'}}
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
                
                <!-- Action Required Section -->
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
                                                    <strong>📝 إجراءات مطلوبة:</strong></p>
                                                <br>
                                                <ul style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;line-height:21px;color:#333333;text-align:right;direction: rtl;margin:0;padding-right:20px;">
                                                    <li style="margin-bottom:8px;">مراجعة معلومات المستخدم الجديد</li>
                                                    <li style="margin-bottom:8px;">التحقق من صحة البيانات المدخلة</li>
                                                    <li style="margin-bottom:8px;">تفعيل الحساب إذا لزم الأمر</li>
                                                    <li style="margin-bottom:8px;">إرسال رسالة ترحيب إضافية إذا رغبت</li>
                                                </ul>
                                                <br>
                                                <p style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;line-height:21px;color:#666666;text-align:right;direction: rtl;">
                                                    هذا إشعار تلقائي من نظام <strong>Dreams SMS</strong>. يرجى مراجعة لوحة التحكم للمزيد من التفاصيل.</p>
                                                <br>
                                                <p style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:12px;line-height:18px;color:#999999;text-align:right;direction: rtl;">
                                                    تم إرسال هذا الإشعار تلقائياً من نظام Dreams SMS. لا تحتاج للرد على هذه الرسالة.</p>
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
