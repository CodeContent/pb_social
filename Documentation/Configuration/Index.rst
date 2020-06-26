.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../Includes.txt


.. _configuration:

Configuration Reference
=======================

Dear Typo3 Integrator,

the next steps will hopefully explain you how to use this extension.

1. Go to the extension manger, install this extension and open it's configuration panel.
   For projects in composer mode "composer require plusb/pb_social" should do the trick.
2. Enter all your available social media account data into the respective input fields. 
   If you're new to this or don't have the data, the following links will give you a 
   base direction where to get these credentials.

   - developers.facebook.com/apps
   - code.google.com/apis/console/ (Public Api-Access -> Generate New Key -> Server Key)
   - instagram.com/developer/clients/manage/
   - dev.twitter.com/apps/
   - tumblr.com/oauth/apps
   - developers.pinterest.com/apps/
   - api.imgur.com/
   - linkedin.com/developer/apps

    You might need to grant special permissions and add users to your app etc.
    All the details should be documented on the pages above.
    If you encounter any difficulties, check the FAQ section or contact us at hello@plusb.de
    With version 1.2.7 you will be able to integrate tx_news posts into the feed.
    It is possible to display news by category. The plugin needs a news plugin running on any other site to generate detail view links.
    Just make sure tx_news is installed and running and you have some news to display.
3. Include the extension typoscript
4. Navigate to an empty page and insert the "Socialfeed"-Plugin
5. Open the flexform and navigate through the Provider-Tabs you want to activate.

   - find facebook id => https://findmyfbid.com/
   - find instagram id => https://codeofaninja.com/tools/find-instagram-user-id
   - find google+ id => http://ansonalex.com/google-plus/how-do-i-find-my-google-plus-user-id-google/

6. You can use multiple search values by making a comma separated string
7. Add the Scheduler Task "Extbase CommandController Task" and choose "PbSocial PbSocial: updateFeedData" (Note: the frequency should be set to a relatively small value, because of the flexform property "Feed update interval" that is controlling the refresh rate, too. On the other hand most APIs restrict requests to every 10-15 minutes. Be sure to respect those request limits. A scheduler task that runs every minute will only fill your error_log.)
7.1. check that you have a backend user called "_cli_scheduler"
7.2. check that you have a cron-job, that calls "./typo3/cli_dispatch.phpsh scheduler" from your project's root directory
8. clear all caches and enjoy the result.
8.1 Feed-Caching by the CommandController is saved in the system-cache. Clearing the System cache will also clear your posts. Be sure to run your scheduler task again after every system cache clear command.
9. If you get the following error in the PHP error log: "Error: SSL certificate problem: unable to get local issuer certificate". This happens due to an outdated root certification authority file (cacert.pem). Check these links for further details:

   - https://curl.haxx.se/docs/sslcerts.html
   - https://curl.haxx.se/ca/cacert.pem
   - TL:DR http://flwebsites.biz/posts/how-fix-curl-error-60-ssl-issue
   - add/upload a valid cacert.pem file to your php root directory and add the following line to the php.ini file
   - curl.cainfo="PATH TO\php\cacert.pem"

   For a quick an dirty solution we included a checkbox in the extension configuration that turns off ssl verification for all pb_social requests
   ATTENTION: Activating this checkbox might be a potential security risk!
10. Testing the Scheduler Task
    For testing you can execute the single controller command from the cli via:
    ./typo3/cli_dispatch.phpsh extbase pbsocial:updatefeeddata
    Called from your project's root directory.
    This hint should give you an example how to add a test to your scheduler https://wiki.typo3.org/CommandController_In_Scheduler_Task
    Be sure the scheduler extension is installed, you have a backend user named _cli_lowlevel and your crontab executes ./typo3/cli_dispatch.phpsh periodically.


.. _configuration-faq:

FAQ
---

**Nothing or only fragments visible?**

- clear all cache
- check if some kind of Adblock is running
- check log file (/typo3temp/log/typo3.log)

**Feeds not getting updated ?**

- check typo3 log (/typo3temp/log/typo3.log)
- database table "tx_pbsocial_domain_model_item" ... check the date field of your feed.

**Feeds not refreshing fast enough?**

- check the flexform at the general tab. Is the "refresh time in minutes" correct set? (minimal value 10min)

**Feeds not interacting with your clicks?**

- check if jQuery is installed and ready.

**Strange php errors?**

- check if curl is enabled on your server

**Your Instagram feed should work but the plugin can't find the user you're looking for?**

- Maybe you are running your Instagram app in sandbox mode. That should be no problem, as long as you can invite
the users whose feed you want to display. Read more about sandbox mode here: https://www.instagram.com/developer/sandbox/
   
**Where do I get an Instagram access code from?**

- The link you need should be available above the input field for your access code. Simply replace the parts "YOUR_CLIENT_ID" and "YOUR_REDIRECT_URI"
with your data from the Instagram developer console and the link should work. Open the link and copy the access code. You will find the access code
at the end of the url in your browser's address bar.
   
**The Instagram feed can't display a user's posts?**

- If your Instagram app is still in sandbox mode, you have to send a sandbox invite to the user you want to get posts from. Instagram's policy has changed recently
so you'll now have to invite users to your sandbox in roder to get their posts. In addition please check the profile in the app, that is publishing the pictures: the option "private account" has to be deactivated.

**My page doesn't even redirect! What is my redirect uri?**

- The redirect uri is just an obligatory value you must provide to be able to authenticate via OAuth. Simply type in the base url of the page you use the plugin for
or your business homepage. The APIs just need an url they can send the access code to, so just provide any url you like in the respective developer console.

**How do I setup LinkedIn?**

- For showing LinkedIn company posts, you need to be administrator of this company. In the developer backend you'll need to set permissions "r_basicprofile" and "rw_company_admin".
After setting up permissions proceed by following steps 1 to 3 of this manual: https://developer.linkedin.com/docs/oauth2 (as usual redirect URL can be any url since you only need the given codes).
It is important to exchange the authorization code, obtained in step 2, for an access token very quickly, since these codes expire after approximately 20 seconds.
Tools like postman can be useful for this but are not necessary.
