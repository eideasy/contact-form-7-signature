=== Qualified Electronic Signatures by eID Easy ===
Plugin Name: Qualified Electronic Signatures by eID Easy
Contributors: EID Easy OÜ
Plugin URL: https://eideasy.com
Tags: fluent forms, qualified signature, electonicsignature, digitalsignature, esignature, signature, electronic signature, digital signature, qes, asice, bdoc, pades, xades, cades, eidas
Requires at least: 4.5
Tested up to: 6.5
Stable tag: trunk
License: GPLv3

== Description==
This plugin will help you add qualified signatures to the PDF files created from the Contact From 7 responses.

Feature requests and questions to: support@eideasy.com

It is using service and API-s from https://eideasy.com. To activate the signing service is needed to create user account and copy credentials from there into the plugin configuration.

1. After the CF7 form is submitted then eID Easy hooks into the process, takes the generated PDF and prepares it for signing.
2. After submission user is redirected to the electronic signature creation page.
3. After user has created electronic signature he is redirected back to the page specified in the configuration
4. New pending contract is created in the admin where service provider can add its signature
5. Once both sides have signed then created .asice container will be sent to both sides e-mail

== Installing and requirements ==
1. Contact Form 7 or Fluent Forms must be installed
2. If Contact Form 7 is used then it must have addon that will create PDF from the form fields. For example "PDF Forms Filler for Contact Form 7" or "Send PDF for Contact Form 7".
3. If Fluent Forms in used then make sure e-mail with PDF attachment notifications is configured. Use "Fluent Forms PDF Generator" plugin. You might need to download fonts for the plugin.
4. Account must be created at https://eideasy.com
5. This plugin will take first PDF attachment from the e-mail and start signing that.
6. If the signature view will not be shown then make sure that the notification e-mail has PDF attached, otherwise there is nothing to sign.

== Usage instructions ==
1. Copy and paste CF7 form ID-s to configuration where attachments will be signed
2. Configure other checkboxes and fields in the admin page. Follow help texts.

eID Easy terms and conditions can be found here https://eideasy.com/terms-of-service/, privacy policy here https://eideasy.com/privacy-policy/

== Screenshots ==
1. Admin view

== Changelog ==

= 3.3.1 =
Remove third party polyfill for fetch and Promise.

= 3.3.0 =
Add the option to include the signed document with the service provider notification email.

= 3.2.1 =
Fix an incorrect setting text: "If checked then notification e-mail with signed file will be sent for every signature created."
Correct is: "If checked then notification emails are not sent to the eID Easy account owner"

= 3.2 =
Support for signing uploaded files together with the generated PDF

= 3.1.1 =
Tested with WP 5.9

= 3.1 =
Option to choose service provider e-mail based on specific field value

= 3.0.1 =
All generated PDF files will be signed not only the first one

= 3.0.0 =
Fluent Forms support added.

= 2.3.3 =
Signed filed download and admin e-mail notification are configurable

= 2.3.2 =
Compatible with latest version of CF7

= 2.3.1 =
Forms without signatures needed work also

= 2.3.0 =
Working better with "Send PDF for Contact Form 7"

= 2.2.0 =
Users can sign PDF-s created form CF7 submissions or choose to skip digital signing. Service providers will be able to add their side signatures as well.
