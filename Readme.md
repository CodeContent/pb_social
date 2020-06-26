# TYPO3 Extension plusb/pb_social

> Social media stream enables you to get posts from social media networks into your TYPO3 system.
> Currently supported social media networks are: Facebook, Imgur, Instagram, LinkedIn, Pinterest, Tumblr, Twitter, YouTube, Vimeo and TYPO3 Extension tx_news

With this extension we decided to fill a gap of social media integration in TYPO3. Our goal is to provide an easy and time-saving way of integrating and interacting with common social media platforms. Nothing is perfect (as you know) but we will give our best to make this extension as comfortable as you need it. For that we need your feedback, so if you need anything or would just like to comment on, don't hesitate to contact us. Simply write an email to <hello@plusb.de>.

> Please visit our homepage [plusb.de](https://www.plusb.de/log/social-media-streams-pb_social/ "find more at our home page")

It can display feeds from social media networks in the way you like it. Maybe you want to get your
Facebook-Page content? No problem, set your Facebook-Credentials, file for app review with 'managed-pages' grants only, and you're ready to go.
The Extension will do all the tricky authentication stuff and leave the relaxed part of making the result pretty to you.

Sometimes you'll need to follow a link to generate access codes for our plugin. 
> Be sure that **we don't have access** to any of **your sensitive data**. 

If you do not change code, **everything of pb_social extension is stored in your TYPO3 database and in your TYPO3 file system!** 

The reason why you need to provide these access codes is that most social media networks use OAuth2 authentication.
Read more about OAuth here: http://oauth.net/articles/authentication/

## 1. Features

* different configuration and social media networks on each plugin and on different pages
* [new] different social media network credentials on each plugin, overriding optional Extension Configuration credentials (global) 
* scheduler cron job to fetch social media network data according to configuration of plugins
* Request limit for posts and filter setting for each social media network on each plugin
* *tx_news* api access, you can include your own tx_news items in your feeds [learn more about extension *tx_news* of Georg Ringer](https://github.com/georgringer/news)
* currently supported social media networks: Facebook, Imgur, Instagram, LinkedIn, Pinterest, Tumblr, Twitter, Youtube, Vimeo, tx_news 

## 2. Usage
### 2.1. Installation
#### Installation using Composer
  
Install the extension by using [Composer](https://getcomposer.org/ "Learn more about composer"). 
In your Composer based TYPO3 project root, just do `composer req plusb/pb_social`.
The current version has an issue with composer. [View Issue](https://bitbucket.org/plus-b/pb_social/issues/14/composer-warning-ambiguous-class).

#### Installation as extension from TYPO3 Extension Repository (TER)

Download and install the extension with the extension manager module.

### 2.2. Minimal setup

1. Include the TypoScript in your root template record or in template record of the page containing a pb_social plugin. 
1. Go to extension configuration of pb_social in Admin Tools / Extensions / pb_social, click on gear wheel icon.
    * Get some API credentials of a social media network and enter it in extension configuration
1. Create a plugin of pb_social on a page and set this social media network to "Active" by clicking checkbox, save plugin changes (perhaps you want to insert some further data like a search id or sth. else)

### 2.3 Detailed Setup 

1. Enter all your available social media account data into the respective input fields. If you're new to this or don't have the data, the following links will give you a basic direction where to get these credentials. [developers.facebook.com/apps](developers.facebook.com/apps), 
    [instagram.com/developer/clients/manage/](instagram.com/developer/clients/manage/), 
    [dev.twitter.com/apps/](dev.twitter.com/apps/), 
    [tumblr.com/oauth/apps](tumblr.com/oauth/apps), 
    [developers.pinterest.com/apps/](developers.pinterest.com/apps/), 
    [api.imgur.com/](api.imgur.com/), 
    [linkedin.com/developer/apps](linkedin.com/developer/apps)
You might need to grant special permissions and add users to your app etc. 
All the details should be documented on the pages above. If you encounter any difficulties, check the FAQ section or contact us at hello@plusb.de
With version 1.2.7 you will be able to integrate tx_news posts into the feed.
It is possible to display news by category. The plugin needs a news plugin running to generate detail view links.
Just make sure tx_news is installed and running and you have some news to display.
1. Include the extension typoscript
1. Navigate to an empty page and insert the "Socialfeed"-Plugin
1. Open the flexform and navigate through the Provider-Tabs you want to activate.
1. You can use multiple search values by making a comma separated string
1. Add the Scheduler Task "Extbase CommandController Task" and choose "PbSocial PbSocial: updateFeedData" (Note: the frequency should be set to a relatively small value, because of the flexform property "Feed update interval" that is controlling the refresh rate in your frontend template. On the other hand most APIs restrict requests to every 10-15 minutes. Be sure to respect those request limits. A scheduler task that runs every minute will only fill your error_log. A good value is 15 minutes.)
    * to set up a cronjob for TYPO3, please refer to https://docs.typo3.org/typo3cms/extensions/scheduler/Installation/CronJob/Index.html
          
1. Clear all caches and enjoy the result.
   * Feed-Caching by the CommandController is saved in the system-cache. Clearing the System cache will also clear your posts. Be sure to run your scheduler task again after every system cache clear command.
1. If you get the following error in the PHP error log: "Error: SSL certificate problem: unable to get local issuer certificate". 
   This happens due to an outdated root certification authority file (cacert.pem).
   Check these links for further details:
    [https://curl.haxx.se/docs/sslcerts.html](https://curl.haxx.se/docs/sslcerts.html)
    [ttps://curl.haxx.se/ca/cacert.pem](https://curl.haxx.se/ca/cacert.pem)  
    [TL:DR http://flwebsites.biz/posts/how-fix-curl-error-60-ssl-issue](http://flwebsites.biz/posts/how-fix-curl-error-60-ssl-issue)
    add/upload a valid cacert.pem file to your php root directory and add the following line to the `php.ini` file `curl.cainfo="PATH TO\php\cacert.pem"`
   For a quick an dirty solution we included a checkbox in the extension configuration that turns off ssl verification for all pb_social requests
   ATTENTION: Activating this checkbox might be a potential security risk!
1. Testing the Scheduler Task
    For testing you can execute the single controller command from the cli via:
    `./typo3/cli_dispatch.phpsh extbase pbsocial:updatefeeddata` or `./vendor/bin/typo3 pb_social:pbsocial:updatefeeddata`
       
1. In case of trouble: please check typo3temp/var/logs/typo3_pb_social_*.log or Syslog (SYSTEM/Log in TYPO3 Backend). Please make sure, that you have `$GLOBALS['TYPO3_CONF_VARS']['SYS']['systemLogLevel']` in LocalConfiguration.php set to 0 to see trouble and success. And please do not forget to reset systemLogLevel to a higher value in productive mode.    

### 2.4 Annotations about flexform settings

In tab "General Settings", you see several "global" instructions for the actual plugin scope:

* Turn on TYPO3 Backend logging (sys_log) for this plugin
Logging is written to typo3temp/var/logs/typo3_pb_social_{year-week}.log every week new file accoding to your TYPO3 configuration 'SYS' 'systemLogLevel'. But for users who use multiple plugins in one installation wanted to have an option to stop writing issues to sys-log in backend, not to overflow it. If you check this checkbox every issue of activated social media network this plugin are written to sys-log in backend. 

* Feed update interval (in minutes) 
This integer represents minutes for earliest possible update by page-reload or scheduler work.

* Request limit for posts (small amount -> fast response) 
An integer for a maximal amount of items you call from social media network apis of this plugin. 

* Show only posts with pictures 
If you want to leave out social media posts, which have no image, you uses this checkbox. 

* Trim text after reaching this letter limit 
Here you can crop post descriptions in your plugin teaser. 

## 3. Hints for social media networks
     
### facebook
* https://developers.facebook.com/apps
* pb_social uses Facebook SDK for PHP v5.7.0 - this one goes along with facebook Api v5.0

#### You will need a so called "app review"
1. Please register an account at developers.facebook.com and register your pb_social extension as app.

1. You will need a Facebook Page of your own. Make a note of the Page-ID, which you will need for the extension.

2. You will need to create an App on developers.facebook.com. In most cases, you would need to ask for the proper rights/permissions to make API calls. However, if you are only using the App for pb_social and you are using it in development mode, you won’t need to worry about permissions. Image 2 shows where to find what mode you are currently in. Make a note of the App ID and App Secret. You will need to enter these into the extension.

3. The next steps will be a bit more complicated - but useful: First, navigate to [Developers Facebook Tools](https://developers.facebook.com/tools/explorer/?classic=0, "Facebook Tools").
On the right side of your screen select the App and select **Get Page Access Token** in the **User or Page** section. A popup will appear. Navigate through the steps, checking all of the checkboxes, etc.
The main screen will subsequently change, updating the Key in the Access Token field. Now copy this Key to your clipboard and navigate to [Developers Facebook Tools Accesstoken Debugger](https://developers.facebook.com/tools/debug/accesstoken/, "Accesstoken Debugger"). Paste the copied Key into the given field. 
Clicking the Debug button will reveal Information about the Key you entered. You will see that the Key will expire in just under an hour.
You will, however, need a *longer-lasting* Key for pb_social. To solve this problem, click the button named **Extend Access Token** at the bottom of your screen.
Clicking the button will reveal a new Key. Click the **Debug** button next to it and copy the new Key. This key will last about 2 months.
You can now *navigate back* to [Developers Facebook Tools](https://developers.facebook.com/tools/explorer/?classic=0, "Facebook Tools")
Now choose the *User Token option* under **User or Page* and paste the new Key into the **Access Token** field. 
In the API-Explorer bar enter the Page-ID from Step 1 as follows: **{YOUR-PAGE-ID-FROM-STEP-ONE}/**
Click Submit on the top right of your screen. This API call will change the expiration of your Key from 2 months to **Expires: Never**.
Make sure this is the case by entering the Key on [Developers Facebook Tools Accesstoken Debugger](https://developers.facebook.com/tools/debug/accesstoken/, "Accesstoken Debugger") 
You now have a Key that won’t ever expire. Who knows what the implications are for Facebook. Enter your Key into the **Page Access Token** field in your TYPO3 pb_social extension plugin or extension configuration.

1. Follow up: Copy 'app id' and 'app secret' in app details of https://developers.facebook.com/apps, enter those into pb_social extension, and you can immediately use it as facebook developer mode and see an example in TYPO3 front end.    
1. To move over to production mode of your facebook app: Please file a request for access rights under menu "App Review" using following steps: Please navigate to *App Review". As access rights, please choose **manage_pages**. 

1. You will need a TYPO3 Backend for the app review team to be able to consider you as a good guy. They will need access to your pb_social Plugin settings. Please provide a TYPO3 BE url, BE user and a password for facebook app review team. 

1. You will need a screencast of your backend which shows how to click form BE login to edit of plugin on tab "facebook"  (make sure, that they can not do other things in your backend). 
		
1. Please tell them this one in the text box: 

> For example, feed items of my Facebook page ID get('/{fill in your page id}/feed') are queried at regular intervals. 
>
> The following data are queried: id,message,picture,created_time,full_picture of our posts on page. 

> The Typo3 Administrator includes the TYPO3 extension pb_social https://extensions.typo3.org/extension/pb_social/. 
Then he registers this extension as an app in developers.facebook.com and adds the facebook app id and the facebook app secret to TYPO3.  
On the front page of website the feed items from our facebook page are displayed: id,message,picture. 
> 
> A login to Facebook does not take place, only an page-access-token is used. 

* Instead of writing a story, you could try to just provide url, BE user and password

After a few days (2 hours was my fastest try) they will give you the grants you need - so...

* Please set status of your facebook app under (https://developers.facebook.com/apps) App Review to "on" in the top menu line. 

#### Can I use my own facebook parameter list?

* First: Yes, but please know, what you are doing. Defaults are tested, and good. 
* In your TypoScript constants you can configure `plugin.tx_pbsocial.settings.facebook.requestParameterList`. 
* You can add (but don't need to) a parameter by using "addToList()" in TypoScript Setup e.g.: `plugin.tx_pbsocial.settings.facebook.requestParameterList := addToList(status_type)`.
    * If TypoScript appending methods do not work for you, copy the default string and append your parameters after this string by clearly comma separation. (But `addToList()` above is coolest way to do it).
    * If you use "comments" and "reactions", you will have to request for "user_likes" in https://developers.facebook.com/apps "App Review" (see above). 
    * Important: "id" and "message" are always prepended in list (so do not repeat) and you will not find it in the TypoScript Constant string above. It is prepended in php.
    * To pull up your own parameter according to https://developers.facebook.com/docs/workplace/integrations/custom-integrations/reference/
    * Please consider to change the extension fluid template Partials\Feed\Provider-facebook.html as well! Your request parameter is only shown if you note it down there:
        * to show values always prepend `"feed.raw"`: `{feed.raw.my_facebook_parameter_i_desire}`
    	    * e.g. `{feed.raw.status_type}`
    * To change a fluid template, please copy it to your own configuration area, so it won't get overwritten after an update: Read quickly [Extending an Extbase extension](https://docs.typo3.org/typo3cms/ExtbaseGuide/Extbase/ExtendExtbaseExtension.html, "how to do it")


### instagram
* Instagram code: instagram.client.access_code
    * Instagram code: Retrieve code with the following URL (all characters after "code"):
    
      https://api.instagram.com/oauth/authorize?client_id=YOUR_CLIENT_ID&redirect_uri=YOUR_REDIRECT_URI&response_type=code&scope=public_content
     
     * In sandbox mode use this URL instead:
     
       https://api.instagram.com/oauth/authorize?client_id=YOUR_CLIENT_ID&redirect_uri=YOUR_REDIRECT_URI&response_type=code

* Instagram access token: instagram.client.access_token 
    * Retrieve access token with the following URL (all characters after "token"): 
    
      https://api.instagram.com/oauth/authorize/?client_id=YOUR_CLIENT_ID&redirect_uri=YOUR_REDIRECT_URI&response_type=token 
    * Remark: The checkbox "Disable implicit OAuth" in the instagram developer options backend should not be set 

### LinkedIn
*   https://www.linkedin.com/developer/apps
*   access token: linkedin.access_token
    * Retrieve code and token with the following URLs: Access code (all characters after "code"): https://www.linkedin.com/oauth/v2/authorization?response_type=code&client_id=YOUR_CLIENT_ID&redirect_uri=YOUR_REDIRECT_URI&scope=rw_company_admin,r_basicprofile
    * You'll need to activate "rw_company_admin" and "r_basicprofile" scopes first (LinkedIn developer backend).
    * Please have in mind that the access code is only valid for approxametely 20 seconds. Immediately after generating the code you need to retrieve an access token via:
     https://www.linkedin.com/oauth/v2/accessToken?grant_type=authorization_code&code=GENERATED_CODE&redirect_uri=YOUR_REDIRECT_URI&client_id=YOUR_CLIENT_ID&client_secret=YOUR_CLIENT_SECRET
     
#### How do I setup LinkedIn?
* For showing LinkedIn company posts, you need to be an administrator of this company. In the developer backend you'll need to set permissions "r_basicprofile" and "rw_company_admin".
After setting up permissions proceed to the following steps 1 to 3 of this manual: https://developer.linkedin.com/docs/oauth2 (as usual redirect URL can be any url since you only need the given codes).
It is important to exchange the authorization code, obtained in step 2, for an access token very quickly, since these codes expire after approximately 20 seconds.
Tools like postman can be useful for this but are not necessary.

### Pinterest
*   https://developers.pinterest.com/apps/
*   auth code: pinterest.app.code
    * https://api.pinterest.com/oauth/?response_type=code&redirect_uri=YOUR_REDIRECT_URI&client_id=YOUR_APP_ID&scope=read_public
    * all characters after "code"
         
### twitter
*    https://dev.twitter.com/apps/

### Youtube
*   https://code.google.com/apis/console/

### Vimeo
*   https://developer.vimeo.com/api

### Tumblr
*   http://www.tumblr.com/oauth/apps

### imgur 
*   https://api.imgur.com/



## 4. How To Migrate

### 4.1 Migration from `1.3.1` to `1.3.4`: Instagram access token
* Instagram changed its access token procedure. You seemingly would need a logged in browser session to get the access token. 
So automatic generation by our code will not longer work. To get it, please log in into your instagram and refer to 
information of [instagram developer information](http://instagram.com/developer/clients/manage/). But please notice, you would need
to check "Disable implicit OAuth:" for this time (please uncheck after having done successfully). 

After having received the access token, fill in this long string into `pb_social extension configuration` in tab `"Instagram"` / `"Instagram access token"` and hit "Save". 
* If you did not already have done this, leaving version 1.3.1: Please do this to all of your pb_social - plugins: Open, save it again. We changed flexform in tab "LinkedIn". (If you do not use LinkedIn, nothing has to be done)

## 5. Known Issues

- linkedIn causes some issues, in case a "LinkedIn company ID" does not exist or `Show job postings` and `Show product postings` is activated in plugin settings. 
    * hint: in this case, try to uncheck both `Show job postings` and `Show product postings` in your plugin settings, clear cache of this page and try a reload.
- Setting up plugin by TypoScript only would need to have many TypoScript setting parameters for getting a social media network run, could be confusing. 
- refactor caching of feed items. remove from system cache and integrate into new pb_social cache.

- If you are testing the extension locally, you may encounter some small problems:
    - Facebook posts may not be loaded because of some xampp malconfiguration. You have two choices here:
        - Update your ssl certificates (.cert files) or Turn off ssl verification (considered as unsafe method because you'll send your credentials unencrypted) - See FAQ for details
        - Private Facebook profile posts may be not displayed.

- Posts without an image may not load the placeholder image because of a 'Not allowed to load local resource' error.
- Depending on your Instagram developer app status, you may not be allowed to get data from other users. See the FAQ section for more information.
- Clearing the system cache, will also clear all posts, a white page could result until posts are reloaded. 
- Scheduler Task should only run each 10-15 minutes due to API restrictions.


## 6. FAQ

#### Q: How can I get in contact to plus B, in case of suggestions, trouble or need help?
* Go to [our Website](https://plusb.de/ "our Website plus B in Berlin, Germany")
* write an Email to <hello@plusb.de>
* create an issue at [our Bitbucket Repository](https://bitbucket.org/plus-b/pb_social/issues?status=new&status=open "create Issue")

#### Q: Where do I find error logs?
* Please go to TYPO3 Backend and click in Admin Tool "System" on "Log"
* Please refer to (/typo3temp/log/) on your Webserver in your document root/ project folder

#### Q: Do you see nothing on your page?
* Perhaps you use an ad blocker like Adblock or uBlock in your browser?

#### Q: Your feeds are updated not often enough?
* Go to your plugin configuration and have a look at database field "General". Check entry at `Feed update interval (in minutes)` (Minimum: 10 minutes).  
* Go to your database and check date field of table `tx_pbsocial_domain_model_item`

#### Q: Your feeds are not clickable?
* check if jQuery is loaded and check JavaScript errors.
* check if jQuery is probably loaded twice and switch off jQuery loading in pb_social: TypoScript Constant `plugin.tx_pbsocial.settings.load-jquery = 0`
    * If you need general help for this: [Declaring constants for the Constant Editor](https://docs.typo3.org/typo3cms/TyposcriptSyntaxReference/7.6/TypoScriptTemplates/TheConstantEditor/Index.html)

#### Q: Can I stop pb_social to include jQuery and include it by myself?
* configure TypoScript Constant `plugin.tx_pbsocial.settings.load-jquery = 0`

#### Q: Are there limitation to the number of comments / likes shown on posts?
* Likes and comments of Facebook posts are limited to 70. Posts with more than 70 likes/comments are marked as 70+.

#### Q: Do all social media networks need a numeric channel ID/ search ID in my plugin settings?
* Youtube and Vimeo channel IDs do not necessarily have to be a numeric value.
* Facebook search IDs is a name.
* Instagram search IDs is a numeric value.
* LinkedIn company ID is a numeric value.
    
#### Q: How many tags can I enter in Tumblr plugin settings?
* Tumblr posts can only be filtered by one of the first five tags, because only the first 5 of your tags are searchable on Tumblr.

#### Q: What can I do when nothing or only fragments are visible?
* Clear all cache
* Check if some kind of Adblock is running
* Check TYPO3 System log in Backend 
* Check log file (/typo3temp/log/typo3.log)
		   
* Database table "tx_pbsocial_domain_model_item" ... check the date field of your feed.

#### Q: Feeds not refreshing fast enough?
* check the flexform at the general tab. Is the "refresh time in minutes" correct set? (minimal value 10min)
* check update interval in your scheduler task. Both values are "working together".
			  
		   

#### Q: Strange php errors?
* check if curl is enabled on your server

#### Q: Your Instagram feed should work but the plugin can't find the user you're looking for?
* Maybe you are running your Instagram app in sandbox mode. That should be no problem, as long as you can invite
the users whose feed you want to display. Read more about sandbox mode here: https://www.instagram.com/developer/sandbox/
   
#### Q: The Instagram feed can't display a user's posts?
* If your Instagram app is still in sandbox mode, you have to send a sandbox invite to the user you want to get posts from. Instagram's policy has changed recently
so you'll now have to invite users to your sandbox in order to get their posts. In addition please check the profile in the app, that is publishing the pictures: the option "private account" has to be deactivated.

#### Q: My page doesn't even redirect! What is my redirect uri?
* The redirect uri is just an obligatory value you must provide to be able to authenticate via OAuth. Simply type in the base url of the page you use the plugin for your business homepage. 
The APIs just needs an url they can send the access code to, so just provide any url you like in the respective developer console.
