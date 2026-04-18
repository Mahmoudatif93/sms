<!-- Email Body Component with Image on Left and Text on Right -->
<table class="body" role="presentation" cellpadding="0" cellspacing="0" width="100%" style="padding: 20px 40px; font-family: sans-serif;">
    <tr>
        <!-- Left Column for the Image -->
{{--        <td style="width: 50%; padding-right: 20px; vertical-align: top;">--}}
{{--            <img src="{{ asset('images/phone.png') }}" alt="Phone Image" style="max-width: 100%; height: auto; border-radius: 12px; display: block;"/>--}}
{{--        </td>--}}

        <!-- Right Column for Text -->
        <td style="width: 50%; vertical-align: top; text-align: center;"> <!-- Text-align set to center -->
            <!-- Body Text -->
            <p style="font-size: 16px; color: #666666; text-align: center;">
                Welcome to Dreams! <br/>
            </p>

            <p style="font-size: 16px; color: #666666; text-align: center;">
                We're thrilled to have you onboard. <br/>
            </p>

            <p style="font-size: 16px; color: #666666; text-align: center;">
                To start exploring our powerful messaging solutions, please click the button below to set your password
                and activate your account.
            </p>

            <!-- Call to Action Button -->
            <table role="presentation" align="center" cellpadding="0" cellspacing="0" style="margin-top: 20px;"> <!-- align="center" added -->
                <tr>
                    <td style="border-radius: 5px; background-color: #60f;">
                        <a href="{{ $activation_link }}" style="display: inline-block; padding: 10px 20px; font-size: 16px; color: #ffffff; text-decoration: none; border-radius: 5px;">
                            Set Your Password
                        </a>
                    </td>
                </tr>
                <tr>
                    <td style="text-align: center; margin-top: 20px;">
                        <p style="font-size: 16px; color: #666666;">
                            If you have any questions, feel free to contact us. We're here to help!
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
