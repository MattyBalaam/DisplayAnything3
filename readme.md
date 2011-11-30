# DisplayAnything #

A file upload and gallery tool for Silverstripe 2.4+. It's a simple replacement for the ImageGallery module and it's designed to get you up and running with the minimum of fuss, grey hairs and esoteric error messages.

## Features ##
+ Handle file uploads via XHR or standard uploads.
+ Security: uses a configurable mimetype map, not file extensions, to determine an uploaded file type
+ Integration: uses PHP system settings for upload file size
+ Multiple file uploading in supported browsers (Note: not supported in Internet Explorer)
+ Drag and Drop in supported browsers (Chrome, Firefox, Safari and possibly Opera)
+ XHR file uploading
+ Has Zero Dependencies on DataObjectManager or Uploadify
+ 100% Flash Free - no plugin crashes, HTTP errors, I/O errors or other futzing with incomprehensible errors!
+ Import ImageGallery albums and their items to a gallery
+ Uses jQuery bundled with Silverstripe
+ Well documented & clean code with extendable classes
+ $_REQUEST not used
+ Uses  Valum's file uploader (hat tip)
+ Drag and drop sorting of images & files in the gallery
+ File upload progress with cancel option

## TODO ##
+ Client side reminder of file size (per Valums file uploader spec)
+ Testing of uploads in IE8+

## Why? ##
I built DisplayAnything after implementing the ImageGallery module on a large, complex SilverStripe site.
This resulted in me spending most of my time debugging odd pieces of DataObjectManager code and head scratching Uploadify errors. 
I decided 'never again' and DisplayAnything was born.

## Bugs ##
Probably. Check the Issues list.

## MimeTypes ##
DisplayAnything comes preconfigured to accept image uploads (GIF, PNG, JPG). When setting up the field you can pass an array of mimetypes to override/clear these defaults.
See the examples below for more information on mimetype configuration.

## Migration items from the ImageGallery gallery module ##
If DisplayAnything detects an  ImageGallery Album associated with the current page it will prompt if you wish to import images. Migration is additive (does not destroy the old gallery or items).
Choose a gallery from the menu and save the page, successfully imported items will appear in the file list. You can retry the migration at any time.

Once migration is complete you can remove the Image Gallery module as and when you wish.

## Installing ##
+ Grab the source, using Git, Bzr, wget or whatever your poison
+ Install into a directory "display_anything" in the root of your site
+ run /dev/build (admin privileges required) and possibly a ?flush=1
+ implement in the CMS (see 'CMS' below)
+ log into the CMS and start editing

## CMS ##
You can implement a DisplayAnything gallery using the normal getCmsFields() syntax on a Page type:

```php
class MyPage extends Page {
	
	public status $has_one = array(
		'SomeImage' => 'UploadAnythingFile',
		'SomeGallery' => 'DisplayAnythingGallery',
		'SomeVideoGallery' => 'DisplayAnythingVideoGallery',
	);
	
	public function getCmsFields() {
		$fields = parent::getCmsFields();
		
		//GALLERY per page
		$gallery = new DisplayAnythingGalleryField(
			$this,
			'SomeGallery',
			'DisplayAnythingGallery'
		);
		$gallery
			->SetMimeTypes()//provide a list of allowed mimetypes here
			->SetTargetLocation('/some/path/to/a/gallery');//relative to ASSETS_PATH
		$fields->addFieldsToTab('Root.Content.Gallery', array($gallery));
		
		
		//SINGLE field - with a test to see if the page has been saved
		if(!empty($this->ID)) {
			$uploader = new UploadAnythingField($this, 'FeatureImage','Image');
			$uploader->SetMimeTypes(array('text/plain'));//this uploader only allowes plain text uploads
		} else {
			$uploader = new LiteralField("PageNotSavedYet", "<p>The file may be uploaded after saving this page.</p>");
		}
		$fields->addFieldsToTab('Root.Content.Image', array($uploader));
		
		//VIDEO gallery (YouTube only) - a simple extension to the default gallery
		$gallery = new DisplayAnythingGalleryField(
			$this,
			'SomeVideoGallery',
			'DisplayAnythingVideoGallery'
		);
		$gallery
			->SetMimeTypes()
			->SetTargetLocation('videogallery');
		$fields->addFieldToTab('Root.Content.Videos', $gallery);
		
		return $fields;
	}
}
```
## Frontend Templates ##
+ Inumerable gallery plugins exist for lists and viewing of images (Fancybox is good and open source). DisplayAnything stays light and does not bundle any of these galleries. It's up to you to implement the gallery the way you want it (this saves you having to undo & override any defaults DisplayAnything may set).
Here's an example Page control you may like to use as a starting point:

```php
<% if SomeImageGallery %>
	<% control SomeImageGallery %>
		<div id="ImageGallery">
			<h4>$Title</h4>
			<div class="inner">
				<% if GalleryItems %>
					<div id="GalleryItems">
							<ul id="GalleryList">
								<% control GalleryItems %>
									<li class="$EvenOdd $FirstLast"><a href="$URL" rel="page-gallery">$CroppedImage(90,90)</a></li>
								<% end_control %>
							</ul>
					</div>
				<% end_if %>
				<% include Clearer %>
			</div>
		</div>
	<% end_control %>
<% end_if  %>
```

## State ##
+ Currently considered beta although we're eating our own dogfood and are happy with general stability - i.e test in a development setting before deploying.

## Support ##
+ Twitter : @_codem
+ Issues list please for bug reports
+ Commercial support is available on <a href="http://codem.com.au">this and other Silverstripe projects</a>
