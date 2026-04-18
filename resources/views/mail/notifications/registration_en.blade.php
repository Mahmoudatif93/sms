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
                            style="Margin:0;padding-right:20px;padding-bottom:10px;padding-left:20px;padding-top:20px">
                            <table cellpadding="0" cellspacing="0" width="100%" role="none"
                                   style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
                                <tr>
                                    <td align="center" valign="top" style="padding:0;Margin:0;width:560px">
                                        <table cellpadding="0" cellspacing="0" width="100%"
                                               role="presentation"
                                               style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
                                            <tr>
                                                <td align="left"
                                                    style="padding:0;Margin:0;padding-top:5px;padding-bottom:5px">
                                                    <p style="Margin:0;mso-line-height-rule:exactly;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:21px;letter-spacing:0;color:#333333;font-size:14px">
                                                        <strong>Welcome to Dreams!</strong></p>
                                                    <p style="Margin:0;mso-line-height-rule:exactly;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:21px;letter-spacing:0;color:#333333;font-size:14px">
                                                        <strong></strong><br> We are delighted to have you
                                                        join our community.</p>
                                                    <p style="Margin:0;mso-line-height-rule:exactly;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:21px;letter-spacing:0;color:#333333;font-size:14px">
                                                        <br></p>
                                                    <p style="Margin:0;mso-line-height-rule:exactly;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:21px;letter-spacing:0;color:#333333;font-size:14px">
                                                        To complete your registration , please use the
                                                        One-Time Password (OTP) provided below. This code is
                                                        essential for verifying your account and ensuring
                                                        the security of your information.</p>
                                                    <p style="Margin:0;mso-line-height-rule:exactly;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:21px;letter-spacing:0;color:#333333;font-size:14px">
                                                        <br></p>
                                                    <p style="Margin:0;mso-line-height-rule:exactly;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:21px;letter-spacing:0;color:#333333;font-size:14px">
                                                        Thank you for choosing <strong>Dreams</strong> as
                                                        your trusted messaging service provider. We look
                                                        forward to supporting you every step of the way.</p>
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
                            style="Margin:0;padding-top:10px;padding-right:20px;padding-bottom:10px;padding-left:20px">
                            <table cellpadding="0" cellspacing="0" width="100%" role="none"
                                   style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
                                <tr>
                                    <td align="center" valign="top" style="padding:0;Margin:0;width:560px">
                                        <table cellpadding="0" cellspacing="0" width="100%"
                                               style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:separate;border-spacing:0px;border-left:2px dashed #cccccc;border-right:2px dashed #cccccc;border-top:2px dashed #cccccc;border-bottom:2px dashed #cccccc;border-radius:5px"
                                               role="presentation">
                                            <tr>
                                                <td align="center"
                                                    style="Margin:0;padding-top:10px;padding-right:20px;padding-left:20px;padding-bottom:20px">
                                                    <h1 class="es-m-txt-c"
                                                        style="Margin:0;font-family:arial, 'helvetica neue', helvetica, sans-serif;mso-line-height-rule:exactly;letter-spacing:0;font-size:46px;font-style:normal;font-weight:bold;line-height:55.2px;color:#333333">
                                                        <strong
                                                            style="mso-line-height-rule:exactly;text-decoration:none;color:#5C68E2;font-size:46px">
                                                            {{$details['message']}}
                                                        </strong></h1>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
{{--                    <tr>--}}
{{--                        <td align="left"--}}
{{--                            style="padding:0;Margin:0;padding-right:20px;padding-bottom:10px;padding-left:20px">--}}
{{--                            <table cellpadding="0" cellspacing="0" width="100%" role="none"--}}
{{--                                   style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">--}}
{{--                                <tr>--}}
{{--                                    <td align="center" valign="top" style="padding:0;Margin:0;width:560px">--}}
{{--                                        <table cellpadding="0" cellspacing="0" width="100%"--}}
{{--                                               style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:separate;border-spacing:0px;border-radius:5px"--}}
{{--                                               role="presentation">--}}
{{--                                            <tr>--}}
{{--                                                <td align="center"--}}
{{--                                                    style="padding:0;Margin:0;padding-top:10px;padding-bottom:10px">--}}
{{--                                                                <span class="es-button-border"--}}
{{--                                                                      style="border-style:solid;border-color:#2CB543;background:#5C68E2;border-width:0px;display:inline-block;border-radius:6px;width:auto"><a--}}
{{--                                                                        href="" target="_blank" class="es-button"--}}
{{--                                                                        style="mso-style-priority:100 !important;text-decoration:none !important;mso-line-height-rule:exactly;color:#FFFFFF;font-size:20px;padding:10px 30px 10px 30px;display:inline-block;background:#5C68E2;border-radius:6px;font-family:arial, 'helvetica neue', helvetica, sans-serif;font-weight:normal;font-style:normal;line-height:24px;width:auto;text-align:center;letter-spacing:0;mso-padding-alt:0;mso-border-alt:10px solid #5C68E2;border-left-width:30px;border-right-width:30px">Copy Code</a></span>--}}
{{--                                                </td>--}}
{{--                                            </tr>--}}
{{--                                        </table>--}}
{{--                                    </td>--}}
{{--                                </tr>--}}
{{--                            </table>--}}
{{--                        </td>--}}
{{--                    </tr>--}}
                    <tr>
                        <td align="left"
                            style="padding:0;Margin:0;padding-right:20px;padding-left:20px;padding-top:20px">
                            <table cellpadding="0" cellspacing="0" width="100%" role="none"
                                   style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
                                <tr>
                                    <td align="center" valign="top" style="padding:0;Margin:0;width:560px">
                                        <table width="100%" role="presentation" cellpadding="0"
                                               cellspacing="0"
                                               style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
                                            <tr>
                                                <td align="left"
                                                    style="padding:0;Margin:0;padding-bottom:15px"><p
                                                        style="Margin:0;mso-line-height-rule:exactly;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:21px;letter-spacing:0;color:#333333;font-size:14px;text-align:left">
                                                        <strong>Your security is our top priority.</strong>
                                                    </p>
                                                    <p style="Margin:0;mso-line-height-rule:exactly;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:21px;letter-spacing:0;color:#333333;font-size:14px;text-align:left">
                                                        <strong><br></strong></p>
                                                    <p style="Margin:0;mso-line-height-rule:exactly;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:21px;letter-spacing:0;color:#333333;font-size:14px;text-align:left">
                                                        If you did not initiate this request with
                                                        <strong>Dreams</strong>,
                                                        please disregard this email. We take unauthorized
                                                        access seriously and recommend that you remain
                                                        vigilant about any unexpected communications.</p>
                                                    <p style="Margin:0;mso-line-height-rule:exactly;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:21px;letter-spacing:0;color:#333333;font-size:14px;text-align:left">
                                                        <br></p>
                                                    <p style="Margin:0;mso-line-height-rule:exactly;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:21px;letter-spacing:0;color:#333333;font-size:14px;text-align:left">
                                                        Should you have any concerns or suspect any unusual
                                                        activity, do not hesitate to contact our support
                                                        team. We are here to ensure your experience with
                                                        <strong>Dreams</strong> is both safe and secure.</p>
                                                    <p style="Margin:0;mso-line-height-rule:exactly;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:21px;letter-spacing:0;color:#333333;font-size:14px;text-align:left">
                                                        <br></p>
                                                    <p style="Margin:0;mso-line-height-rule:exactly;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:21px;letter-spacing:0;color:#333333;font-size:14px;text-align:left">
                                                        Thank you for your attention to this important
                                                        matter. We appreciate your cooperation in
                                                        maintaining the integrity of our services.</p></td>
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
