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
                                                <p style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:21px;letter-spacing:0;color:#333333;font-size:16px;">
                                                    <strong>🔔 Admin Notification - New User Registration</strong></p>
                                                <p style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:21px;letter-spacing:0;color:#666666;font-size:14px;">
                                                    A new user has registered in the system</p>
                                                <br>
                                                <p style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:21px;letter-spacing:0;color:#333333;font-size:14px;">
                                                    Hello, a new user has registered on the <strong>Dreams SMS</strong> platform. Please review the details below:</p>
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
                                            <td align="left" style="Margin:0;padding:20px;">
                                                <h3 style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:18px;font-weight:bold;line-height:22px;color:#2c3e50;">
                                                    📋 New User Details
                                                </h3>
                                                <br>
                                                <table width="100%" cellpadding="5" cellspacing="0" style="border-collapse:collapse;">
                                                    <tr>
                                                        <td style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;color:#555;font-weight:bold;text-align:left;width:30%;">
                                                            👤 Name:
                                                        </td>
                                                        <td style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;color:#333;text-align:left;">
                                                            {{$details['message']['user_name'] ?? 'Not specified'}}
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;color:#555;font-weight:bold;text-align:left;padding-top:8px;">
                                                            🏷️ Username:
                                                        </td>
                                                        <td style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;color:#333;text-align:left;padding-top:8px;">
                                                            {{$details['message']['username'] ?? 'Not specified'}}
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;color:#555;font-weight:bold;text-align:left;padding-top:8px;">
                                                            📧 Email:
                                                        </td>
                                                        <td style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;color:#333;text-align:left;padding-top:8px;">
                                                            {{$details['message']['email'] ?? 'Not specified'}}
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;color:#555;font-weight:bold;text-align:left;padding-top:8px;">
                                                            📱 Phone Number:
                                                        </td>
                                                        <td style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;color:#333;text-align:left;padding-top:8px;">
                                                            {{$details['message']['number'] ?? 'Not specified'}}
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;color:#555;font-weight:bold;text-align:left;padding-top:8px;">
                                                            🕐 Registration Time:
                                                        </td>
                                                        <td style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;color:#333;text-align:left;padding-top:8px;">
                                                            {{$details['message']['registration_time'] ?? 'Not specified'}}
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;color:#555;font-weight:bold;text-align:left;padding-top:8px;">
                                                            🌐 Site:
                                                        </td>
                                                        <td style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;color:#333;text-align:left;padding-top:8px;">
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
                                                    <strong>📝 Actions Required:</strong></p>
                                                <br>
                                                <ul style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;line-height:21px;color:#333333;text-align:left;margin:0;padding-left:20px;">
                                                    <li style="margin-bottom:8px;">Review the new user information</li>
                                                    <li style="margin-bottom:8px;">Verify the accuracy of entered data</li>
                                                    <li style="margin-bottom:8px;">Activate the account if necessary</li>
                                                    <li style="margin-bottom:8px;">Send additional welcome message if desired</li>
                                                </ul>
                                                <br>
                                                <p style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;line-height:21px;color:#666666;text-align:left;">
                                                    This is an automated notification from the <strong>Dreams SMS</strong> system. Please check the admin dashboard for more details.</p>
                                                <br>
                                                <p style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:12px;line-height:18px;color:#999999;text-align:left;">
                                                    This notification was sent automatically from the Dreams SMS system. No reply is needed for this message.</p>
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
