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
                                                <p style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:21px;letter-spacing:0;color:#333333;font-size:18px;">
                                                    <strong>🎉 Welcome to the Dreams Family!</strong></p>
                                                <p style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:21px;letter-spacing:0;color:#666666;font-size:14px;">
                                                    We're thrilled to have you join us</p>
                                                <br>
                                                <p style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:21px;letter-spacing:0;color:#333333;font-size:14px;">
                                                    Dear <strong>{{$details['message']['user_name'] ?? 'Valued User'}}</strong>,</p>
                                                <br>
                                                <p style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:21px;letter-spacing:0;color:#333333;font-size:14px;">
                                                    Welcome to <strong>{{$details['message']['site_name'] ?? 'Dreams'}}</strong>! We're excited to have you on board and look forward to providing you with the best SMS services.</p>
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
                    <td align="left"
                        style="Margin:0;padding-top:10px;padding-right:20px;padding-bottom:10px;padding-left:20px;">
                        <table cellpadding="0" cellspacing="0" width="100%" role="none"
                               style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
                            <tr>
                                <td align="center" valign="top" style="padding:0;Margin:0;width:560px">
                                    <table cellpadding="0" cellspacing="0" width="100%"
                                           style="border:2px solid #5C68E2;border-radius:12px;background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);"
                                           role="presentation">
                                        <tr>
                                            <td align="center" style="Margin:0;padding:30px 20px;">
                                                <h2 style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:24px;font-weight:bold;line-height:28px;color:#ffffff;">
                                                    🌟 Welcome to Your Journey with Us! 🌟
                                                </h2>
                                                <br>
                                                <p style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:16px;line-height:24px;color:#ffffff;">
                                                    You're now part of the Dreams community
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
                    <td align="left"
                        style="Margin:0;padding-top:20px;padding-right:20px;padding-bottom:10px;padding-left:20px;">
                        <table cellpadding="0" cellspacing="0" width="100%" role="none"
                               style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
                            <tr>
                                <td align="left" valign="top" style="padding:0;Margin:0;width:560px">
                                    <table cellpadding="0" cellspacing="0" width="100%"
                                           role="presentation"
                                           style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
                                        <tr>
                                            <td align="left" style="padding:0;Margin:0;padding-bottom:15px">
                                                <h3 style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:18px;font-weight:bold;line-height:22px;color:#2c3e50;">
                                                    🚀 What You Can Do Now:
                                                </h3>
                                                <br>
                                                <table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;">
                                                    <tr>
                                                        <td style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;color:#333;text-align:left;border-bottom:1px solid #f0f0f0;">
                                                            📱 <strong>Send SMS Messages:</strong> Start sending your messages easily and quickly
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;color:#333;text-align:left;border-bottom:1px solid #f0f0f0;padding-top:8px;">
                                                            📊 <strong>Track Statistics:</strong> Monitor your message performance and delivery rates
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;color:#333;text-align:left;border-bottom:1px solid #f0f0f0;padding-top:8px;">
                                                            👥 <strong>Manage Contacts:</strong> Organize your contact lists efficiently
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;color:#333;text-align:left;border-bottom:1px solid #f0f0f0;padding-top:8px;">
                                                            ⏰ <strong>Schedule Messages:</strong> Schedule your messages for the perfect timing
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;color:#333;text-align:left;padding-top:8px;">
                                                            🎨 <strong>Message Templates:</strong> Use ready-made templates or create your own
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
                    <td align="left"
                        style="padding:20px 20px 0 20px;">
                        <table cellpadding="0" cellspacing="0" width="100%" role="none"
                               style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
                            <tr>
                                <td align="left" valign="top" style="padding:0;Margin:0;width:560px">
                                    <table width="100%" role="presentation" cellpadding="0"
                                           cellspacing="0"
                                           style="border-collapse:collapse;border-spacing:0px;border:1px solid #e0e0e0;border-radius:8px;background-color:#f8f9fa;">
                                        <tr>
                                            <td align="left" style="padding:20px;Margin:0;">
                                                <h3 style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:16px;font-weight:bold;line-height:20px;color:#2c3e50;">
                                                    📋 Next Steps:
                                                </h3>
                                                <br>
                                                <ol style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;line-height:21px;color:#333333;text-align:left;margin:0;padding-left:20px;">
                                                    <li style="margin-bottom:8px;">Log in to your account</li>
                                                    <li style="margin-bottom:8px;">Explore your dashboard</li>
                                                    <li style="margin-bottom:8px;">Add your first contacts</li>
                                                    <li style="margin-bottom:8px;">Send your first test message</li>
                                                    <li style="margin-bottom:8px;">Contact us if you need any help</li>
                                                </ol>
                                                <br>
                                                <p style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;line-height:21px;color:#666666;text-align:left;">
                                                    💡 <strong>Tip:</strong> Start by sending a test message to yourself to make sure everything works perfectly!</p>
                                                <br>
                                                <p style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:14px;line-height:21px;color:#333333;text-align:left;">
                                                    We're here to support you every step of the way. Welcome again to <strong>{{$details['message']['site_name'] ?? 'Dreams'}}</strong>!</p>
                                                <br>
                                                <p style="font-family:arial, 'helvetica neue', helvetica, sans-serif;font-size:12px;line-height:18px;color:#999999;text-align:left;">
                                                    This message was sent automatically from Dreams system. You don't need to reply to this message.</p>
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
