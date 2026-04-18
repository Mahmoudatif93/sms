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
                    <td align="left"
                        style="Margin:0;padding-right:20px;padding-bottom:10px;padding-left:20px;padding-top:20px;">
                        <table cellpadding="0" cellspacing="0" width="100%" role="none"
                               style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
                            <tr>
                                <td align="left" valign="top" style="padding:0;Margin:0;width:560px">
                                    <table cellpadding="0" cellspacing="0" width="100%"
                                           role="presentation"
                                           style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
                                        <tr>
                                            <td align="center"
                                                style="padding:0;Margin:0;padding-top:5px;padding-bottom:5px">
                                                <p style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:24px;letter-spacing:0;color:#333333;font-size:20px;">
                                                    <strong>تصدير معاملات المحفظة</strong></p>
                                                <p style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:21px;letter-spacing:0;color:#666666;font-size:14px;padding-top:10px;">
                                                    ملف التصدير جاهز للتحميل</p>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <!-- Export Info Section -->
                <tr>
                    <td align="left"
                        style="Margin:0;padding-top:10px;padding-right:20px;padding-bottom:10px;padding-left:20px;">
                        <table cellpadding="0" cellspacing="0" width="100%" role="none"
                               style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
                            <tr>
                                <td align="center" valign="top" style="padding:0;Margin:0;width:560px">
                                    <table cellpadding="0" cellspacing="0" width="100%"
                                           style="border:1px solid #e0e0e0;border-radius:8px;background-color:#f9f9f9"
                                           role="presentation">
                                        <tr>
                                            <td align="center" style="Margin:0;padding:30px;">
                                                <p style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;line-height:21px;color:#666666;padding-bottom:20px;direction:rtl;">
                                                    اضغط على الزر أدناه لتحميل ملف تصدير معاملات المحفظة.
                                                </p>

                                                <!-- Download Button -->
                                                <table cellpadding="0" cellspacing="0" align="center" role="presentation">
                                                    <tr>
                                                        <td align="center" style="border-radius:5px;background-color:#6600ff;">
                                                            <a href="{{ $downloadUrl }}"
                                                               style="display:inline-block;padding:14px 30px;font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:16px;font-weight:bold;color:#ffffff;text-decoration:none;border-radius:5px;">
                                                                تحميل الملف
                                                            </a>
                                                        </td>
                                                    </tr>
                                                </table>

                                                <p style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:12px;line-height:18px;color:#999999;padding-top:20px;direction:rtl;">
                                                    ستنتهي صلاحية هذا الرابط خلال ساعة واحدة.
                                                </p>

                                                <!-- Fallback link -->
                                                <p style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:11px;line-height:16px;color:#999999;padding-top:15px;direction:rtl;">
                                                    إذا لم يعمل الزر، انسخ هذا الرابط والصقه في المتصفح:
                                                </p>
                                                <p style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:10px;line-height:14px;color:#6600ff;padding-top:5px;word-break:break-all;direction:ltr;">
                                                    {{ $downloadUrl }}
                                                </p>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <!-- Help Section -->
                <tr>
                    <td align="left"
                        style="padding:20px 20px 20px 20px;">
                        <table cellpadding="0" cellspacing="0" width="100%" role="none"
                               style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
                            <tr>
                                <td align="left" valign="top" style="padding:0;Margin:0;width:560px">
                                    <table width="100%" role="presentation" cellpadding="0"
                                           cellspacing="0"
                                           style="border-collapse:collapse;border-spacing:0px">
                                        <tr>
                                            <td align="center" style="padding:0;Margin:0">
                                                <p style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;line-height:21px;color:#666666;text-align:center;direction:rtl;">
                                                    إذا كان لديك أي استفسار، لا تتردد في التواصل معنا.</p>
                                                <br>
                                                <p style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:12px;line-height:18px;color:#999999;text-align:center;direction:rtl;">
                                                    هذا إشعار تلقائي من نظام <strong>Dreams</strong>.</p>
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
