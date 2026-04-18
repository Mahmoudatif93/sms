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
                                            <td align="left"
                                                style="padding:0;Margin:0;padding-top:5px;padding-bottom:5px">
                                                <p style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:21px;letter-spacing:0;color:#333333;font-size:14px;">
                                                    <strong>Welcome to Dreams!</strong></p>
                                                <p style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:21px;letter-spacing:0;color:#333333;font-size:14px;">
                                                    We are delighted to have you join our community.</p>
                                                <br>
                                                <p style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:21px;letter-spacing:0;color:#333333;font-size:14px;">
                                                    To complete your Login, please use the One-Time Password (OTP) provided below. This code is essential for verifying your account and ensuring the security of your information.</p>
                                                <br>
                                                <p style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:21px;letter-spacing:0;color:#333333;font-size:14px;">
                                                    Thank you for choosing <strong>Dreams</strong> as your trusted messaging service provider. We look forward to supporting you every step of the way.</p>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td align="left"
                        style="Margin:0;padding-top:10px;padding-right:20px;padding-bottom:10px;padding-left:20px;">
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
                                                <h1 style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:46px;font-weight:bold;line-height:55.2px;color:#5C68E2;">
                                                    <strong>{{$details['body']}}</strong>
                                                </h1>

                                                <!-- Expiry note -->
                                                <div style="margin-top:15px;">
                                                    <p class="expiry-note" style="font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;font-size:11px;line-height:16px;color:#6c757d;text-align:center;margin:0;font-weight:400;letter-spacing:0.2px;font-style:italic;">
                                                        ⏰ This code is valid for <span style="color:#dc3545;font-weight:500;">10 minutes</span> only
                                                    </p>
                                                </div>
                                            </td>

                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td align="left"
                        style="padding:20px 20px 0 20px;">
                        <table cellpadding="0" cellspacing="0" width="100%" role="none"
                               style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
                            <tr>
                                <td align="left" valign="top" style="padding:0;Margin:0;width:560px">
                                    <table width="100%" role="presentation" cellpadding="0"
                                           cellspacing="0"
                                           style="border-collapse:collapse;border-spacing:0px">
                                        <tr>
                                            <td align="left" style="padding:0;Margin:0;padding-bottom:15px">
                                                <p style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;line-height:21px;color:#333333;text-align:left;">
                                                    <strong>Your security is our top priority.</strong></p>
                                                <br>

                                                <!-- Security notes -->
                                                <div style="background-color:#f8f9fa;border-left:4px solid #17a2b8;padding:15px;margin:10px 0;border-radius:4px;">
                                                    <p class="note-text" style="font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;font-size:12px;line-height:18px;color:#495057;text-align:left;margin:0;font-weight:400;letter-spacing:0.3px;">
                                                        <span style="color:#17a2b8;font-weight:500;">📝 Important Note:</span> If you did not request this code from <strong>Dreams</strong>, please disregard this email immediately.
                                                    </p>
                                                </div>

                                                <div style="background-color:#fff3cd;border-left:4px solid #ffc107;padding:15px;margin:10px 0;border-radius:4px;">
                                                    <p class="note-text" style="font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;font-size:12px;line-height:18px;color:#856404;text-align:left;margin:0;font-weight:400;letter-spacing:0.3px;">
                                                        <span style="color:#ffc107;font-weight:500;">⚠️ Security Warning:</span> We take unauthorized access attempts seriously. If you notice any unusual activity, contact our support team immediately.
                                                    </p>
                                                </div>

                                                <br>
                                                <p style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;line-height:21px;color:#333333;text-align:left;">
                                                    Thank you for your attention and cooperation in maintaining the security of our services.</p>
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
