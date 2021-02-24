=== eID Easy ===
Plugin Name: Qualified Electronic Signatures for Contact Form 7
Contributors: EID Easy OÜ
Plugin URL: https://eideasy.com
Tags: qualified signature, electonicsignature, digitalsignature, esignature, signature, electronic signature, digital signature, qes, asice, bdoc, pades, xades, cades, eidas
Requires at least: 4.5
Tested up to: 5.6.2
Stable tag: trunk
License: GPLv3

== Description==
This plugin will help you add qualified signatures to the PDF files created from the Contact From 7 responses.

It is using service and API-s from https://eideasy.com. To activate the signing service is needed to create user account and copy credentials from there into the plugin configuration.

1. After the CF7 form is submitted then eID Easy hooks into the process, takes the generated PDF and prepares it for signing.
2. After submission user is redirected to the electronic signature creation page.
3. After user has created electronic signature he is redirected back to the page specified in the configuration
4. New pending contract is created in the admin where service provider can add its signature
5. Once both sides have signed then created .asice container will be sent to both sides e-mail

Support email: info@eideasy.com

== Installing and requirements ==
1. Contact Form 7 must be installed
2. Contact Form 7 must have addon that will create PDF from the form fields. For example "PDF Forms Filler for Contact Form 7" or "Send PDF for Contact Form 7".
3. Account must be created at https://eideasy.com
4. This plugin will take first PDF attachment and start signing that.

== Usage instructions ==
1. Copy and paste CF7 form ID-s to configuration where attachments will be signed
2. Configure other checkboxes and fields in the admin page. Follow help texts.

eID Easy terms and conditions can be found here https://eideasy.com/terms-of-service/, privacy policy here https://eideasy.com/privacy-policy/

== Screenshots ==
1. Admin view

== Changelog ==

= 2.3.0 =
Working better with "Send PDF for Contact Form 7"

= 2.2.0 =
Users can sign PDF-s created form CF7 submissions or choose to skip digital signing. Service providers will be able to add their side signatures as well.
