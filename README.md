# WebServiceTemplate
ProcessWire web service implementation using template

Use template URL segment to identify what page should be returned. All output is generated in JSON format. 


# Supported fields
This are the current supported fields. Feel free to edit this part if the field you are using is supported. 

* Comments
* Image
* Map Marker
* Page
* Repeater
* Text
* Textarea

# Supported Version
This template is tested on ProcessWire version 2.4, 2.5.

# Supported Modules
* CommentRatings

# Installation

###Template
* Add a template **web-service** with no other fields and configure it to allow **URL Segments**
* Add a field name **secret_key** and **site_prefix** (optional)
* Add a template **configuration** and add the field **secret_key** and **site_prefix** (optional)

###Page
All page must be child of the Home

* Add a page with **configuration** as a template and enter your **secret key** to access the content and **site_prefix** (optional) you want to remove
* Add a page with  **web-service** as a template

# Calling
You can call specific page by **http://root/web_service_url/secret_key/page_url** (ex: **http://root/web-service/12345/example**)

#Reserve URL
* Home must be called by using **home** as **page_url** (ex: **http://root/web-service/12345/home**)

# Live Implementation
Using ProcessWire on managing content and Phalcon for getting the data. Our team is currently develop of proof of concept on the new   [MoneyMax PH](http://pocph.compargo.com/) design and on [searcHMO](http://54.200.152.46/searcHMO/).

#Tips
Increase the maximum URL segment to avoid future error. You may modify this setting in your /site/config.php file and add $config->maxUrlSegments = 10 on the end of the line.

#Todo
Automate the installation
