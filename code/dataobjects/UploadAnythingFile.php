<?php
/**
 * UploadAnythingFile()
 * @note our file class, extends File and provides extra bits and bobs
 * @note DisplayAnythingFile extends this class and should be instantiated
 */
class UploadAnythingFile extends File {

	private $meta;
	
	protected static $configuration = FALSE;//for general config

	static $db = array(
		'Visible' => 'Boolean',
		'Caption' => 'Varchar(255)',
		'Description' => 'Text',//separate to File.Content
		//links to other content and calls to action
		'ExternalURL' => 'varchar(255)',
		'CallToActionText' => 'varchar(32)',
		'AlternateURL' => 'varchar(255)',//not in use
	);
	
	static $has_one = array(
		'InternalLink' => 'Page',
	);
	
	static public function Configure($data) {
		self::$configuration = $data;
	}
	
	/**
	 * @returns string
	 * @note returns the link to an ExternalURL or the internal page link
	 */
	public function LinkToURL() {
		if(!empty($this->ExternalURL)) {
			return $this->ExternalURL;
		} else {
			$link = $this->InternalLink()->Link();
			if(!empty($link)) {
				return $link;
			}
		}
		return FALSE;
	}
	
	/**
	 * updateFilesystem()
	 * @note override parent File::updateFilesystem can catch its Exceptions
	 */
	public function updateFilesystem() {
		try {
			parent::updateFilesystem();
		} catch (Exception $e) {
			//ignore what happens above
		}
		return TRUE;
	}
	
	/**
	 * Event handler called before deleting from the database.
	 * @note we test for exceptions here and ignore them.. allowing the record to be deleted.
	 * @note if the file has been remove from the file system updateFileSystem will throw an exception
	 */
	protected function onBeforeDelete() {
		try {
			parent::onBeforeDelete();
		} catch (Exception $e) {}
	}
	
	static public function MimeType($location = "") {
		$mimeType = FALSE;
		if(!is_readable($location)) {
			return FALSE;
		}
		if(function_exists('finfo_open')) {
			//use finfo
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			$mimeType = finfo_file($finfo, $location);
		} else {
			//maybe it's an image..
			$parts = @getimagesize($location);
			if(!empty($parts[2])) {
				$mimeType = image_type_to_mime_type($parts[2]);
			} else if(function_exists('mime_content_type')) {
				$mimeType = mime_content_type($location);
			}
		}
		return $mimeType;
	}
	
	/**
	 * IsImage()
	 * @returns boolean
	 * @note determines if the File is an image
	 */
	public function IsImage($location = "") {
		if($location == "") {
			$location = $this->getFullPath();
		}
		$parts = @getimagesize($location);
		if(!empty($parts[2])) {
			return TRUE;
		}
		return FALSE;
	}
	
	/**
	 * GetMeta()
	 * @note gets meta for current image. If it already exist, return that
	 */
	public function GetMeta() {
	
		if($this->meta) {
			return $this->meta;
		}
		
		$path = $this->getFullPath();
		
		$this->meta = array();
		$this->meta['name'] = $this->Name;
		$this->meta['exists'] = file_exists($path);
		$this->meta['width'] = '';
		$this->meta['height'] = '';
		$this->meta['size'] = $this->getSize();
		$this->meta['mimetype'] = self::MimeType($path);
		if($this->IsImage()) {
			$data = getimagesize($path);
			$this->meta['width'] = $data[0];
			$this->meta['height'] = $data[1];
		}
		return $this->meta;
	}
	
	
	// -- IMAGE THUMBING if this file is an image
	
	/**
	 * CreateImage()
	 * @param $watermark TRUE to return a watermarked image
	 * @param $data array of seed data for the image
	 */
	public function CreateImage($data, $watermark = FALSE) {
		if($watermark) {
			return new WatermarkedImage($data);
		} else {
			return new Image($data);
		}
	}
	
	/**
	 * Link()
	 * @note provides a link to this file, or the watermarked version if required
	 */
	public function Link() {
		if(!empty(self::$configuration['watermark'])) {
			$wm = new WatermarkedImage($this->getAllFields());
			$copy = $wm->SetSize($wm->getWidth(), $wm->getHeight());
			if($copy) {
				unset($wm);
				return $copy->Link();
			}
		}
		return parent::Link();
	}
	
	/**
	 * Thumbnail()
	 * @note helper method to thumb an image to a certain width or height
	 * @param $width_height string WIDTHxHEIGHT e.g 400x300 or a WIDTH e.g 400 -- In templates only two arguments are allowed by SS if you call $Thumbnail
	 * @param $method one of the image thumbing methods supported
	 */
	public function Thumbnail($method, $width_height, $height = 0) {
		if(is_numeric($width_height)) {
			//called from script
			$width = $width_height;
		} else if(strpos( $width_height, "x") !== FALSE) {
			//called from template
			$parts = explode("x", $width_height);
			$width = $height = 0;
			if(!empty($parts)) {
				$width = $parts[0];
				if(count($parts) == 2) {
					$height = $parts[1];
				}
			}
		}
		
		switch($method) {
			case "PaddedImage":
			case "CroppedImage":
				if($width > 0 && $height > 0) {
					return $this->$method($width, $height);
				}
				break;
			case "SetWidth":
				if($width > 0) {
					return $this->SetWidth($width);
				}
				break;
			case "SetHeight":
				if($height > 0) {
					return $this->SetHeight($height);
				}
				break;
			default:
				return "";
				break;
		}
		return "";
	}
	
	public function PaddedImage($width, $height, $watermark = FALSE) {
		$is_image = $this->IsImage();
		if($is_image) {
			$image = $this->CreateImage(array(
				'ID' => $this->ID,
				'Filename' => $this->Filename,
				'Name' => $this->Name,
				'ClassName' => 'Image',
				'Title' => $this->Title,
			), $watermark);
			$resize = $image->PaddedImage($width, $height);
			return $resize->getTag();
		}
		return FALSE;
	}
	
	public function SetWidth($width, $watermark = FALSE) {
		$is_image = $this->IsImage();
		if($is_image) {
			$meta = $this->GetMeta();
			if($meta['width'] < $width) {
				$width = $meta['width'];
			}
			$image = $this->CreateImage(array(
				'ID' => $this->ID,
				'Filename' => $this->Filename,
				'Name' => $this->Name,
				'ClassName' => 'Image',
				'Title' => $this->Title,
			), $watermark);
			$resize = $image->SetWidth($width);
			return $resize->getTag();
		}
		return FALSE;
	}
	
	public function SetHeight($height, $watermark = FALSE) {
		$is_image = $this->IsImage();
		if($is_image) {
			$meta = $this->GetMeta();
			if($meta['height'] < $height) {
				$height = $meta['height'];
			}
			$image = $this->CreateImage(array(
				'ID' => $this->ID,
				'Filename' => $this->Filename,
				'Name' => $this->Name,
				'ClassName' => 'Image',
				'Title' => $this->Title,
			), $watermark);
			$resize = $image->SetHeight($height);
			return $resize->getTag();
		}
		return FALSE;
	}
	
	public function CroppedImage($width, $height, $watermark = FALSE) {
		$is_image = $this->IsImage();
		if($is_image) {
			$image = $this->CreateImage(array(
				'ID' => $this->ID,
				'Filename' => $this->Filename,
				'Name' => $this->Name,
				'ClassName' => 'Image',
				'Title' => $this->Title,
			), $watermark);
			$resize = $image->CroppedImage($width, $height);
			return $resize->getTag();
		}
		return FALSE;
	}
	
	public function WatermarkCroppedImage($width, $height) {
		return $this->CroppedImage($width, $height, TRUE);
	}
	public function WatermarkPaddedImage($width, $height) {
		return $this->PaddedImage($width, $height, TRUE);
	}
	public function WatermarkSetHeight($height) {
		return $this->SetHeight($height, TRUE);
	}
	public function WatermarkSetWidth($width) {
		return $this->SetWidth($width, TRUE);
	}
	
	// -- END IMAGE THUMBING
	
	/**
	 * @note returns the URL to the original, unwatermarked file
	 */
	public function OriginalURL() {
		return  $this->getURL();
	}
	
	/**
	 * @note a template method to use if you are associating a button  with the file, uses these cascaded field values
	 */
	public function LinkToText() {
		if($this->CallToActionText != '') {
			return $this->CallToActionText;
		} else if($this->Caption != '') {
			return $this->Caption;
		} else {
			return $this->Title;
		}
	}
	
	/**
	 * GetMimeTypes() returns the current mimetypes associated with the gallery this file is in
	 * @note this will return an empty array if UploadAnything is in single file mode (has_one)
	 * @return array
	 */
	protected function GetMimeTypes() {
		$mimetypes = array();//use defaults
		try {
			$gallery = $this->Gallery();
			if($gallery && ($usage = $gallery->Usage())) {
				$list = trim($usage->MimeTypes);
				if($list != "") {
					$mimetypes = explode(",", $usage->MimeTypes);
				}
				if(($extra = $this->Gallery()->ExtraMimeTypes) && $extra != "") {
					$mimetypes = array_merge($mimetypes, implode("," , $extra));
				}
			}
		} catch (Exception $e) {
		}
		return array_flip($mimetypes);
	}
	
	/**
	 * FileReplacementField() returns the file replacement field, used to replace this one file in the gallery
	 * @return object
	 */
	protected function FileReplacementField() {
		/*
		$field = new DisplayAnythingGalleryField(
					'ReplaceWith',//name
					'Replace file with',//title
					$this->Gallery() //relatedDataObject
		);
		$field->ReplaceCurrent($this)->OverwriteFile(TRUE)->SetMimeTypes($this->GetMimeTypes());
		$field->show_help = FALSE;
		return $field;
		*/
		return new FileField('ReplaceWith', 'Replace');
	}
	
	public function onBeforeWrite() {
		parent::onBeforeWrite();
		
		//var_dump($_FILES);exit;
		
		if(!empty($_FILES['ReplaceWith'])) {
			$upload = new Upload();
			//TODO - validate the replacement
			$result = $upload->loadIntoFile($_FILES['ReplaceWith'], $this);
			if($result) {
				unset($this->ReplaceWith);
			}
		}
		
		if(!empty($_FILES['AlternateImage'])) {
			$upload = new Upload();
			$image = new Image();
			//TODO - validate the replacement
			$result = $upload->loadIntoFile($_FILES['AlternateImage'], $image);
			if($result) {
				$this->AlternateImageID = $image->ID;
			}
		}
	}
	
	public function getCMSFields() {
		
		$fields = parent::getCMSFields();
		
		$fields->addFieldsToTab(
			'Root.FileInformation',
			array(
				new LiteralField('FilePathField', "<p class=\"message\">Editing {$this->Name} - {$this->Filename}</p>"),
				new TextField('Title', 'Title', $this->Title),
				$this->FileReplacementField(),
				new TextField('Caption', 'Caption', $this->Caption),
				new TextareaField('Description', 'Description', $this->Description),
				new DropDownField('OwnerID','Who owns this file?', DataObject::get('Member')->map('ID','Name'), $this->OwnerID),
				new FileField('AlternateImage', 'Optional Alternate Image'),
			)
		);
		
		$fields->addFieldsToTab(
			'Root.Linking',
			array(
				new TreeDropdownField(
						"InternalLinkID",
						"Internal page link",
						"SiteTree"
				),
				new TextField('ExternalURL', 'External link (e.g http://example.com/landing/page) - will override Internal Page Link', $this->ExternalURL),
				new TextField('CallToActionText', 'Call To Action Text (placed on button or link selected)', $this->CallToActionText),
			)
		);
		
		$meta = $this->GetMeta();
		
		$thumbnail = $this->Thumbnail('SetWidth', 400);
		if(empty($thumbnail)) {
			$thumbnail = self::GetFileIcon();
		}
		
		$warning = "";
		if(!$meta['exists']) {
			$warning = "<p>This file does not exist, it may have been deleted.</p>";
		}
		
		$fields->addFieldsToTab(
			'Root.FilePreview',
			array(
				//and some meta
				new LiteralField(
					'FileMetaData',
<<<HTML
{$warning}
<table class="uploadanythingfile_meta">
	<tbody>
		<tr><th>Name</th><td>{$meta['name']}</td></tr>
		<tr><th>Size</th><td>{$meta['size']}</td></tr>
		<tr><th>Dimensions (WxH)</th><td>{$meta['width']} x {$meta['height']}</td></tr>
		<tr><th>Type</th><td>{$meta['mimetype']}</td></tr>
		<tr><th>Thumbnail</th><td>{$thumbnail}</td></tr>
	</tbody>
</table>
HTML
				),
			)
		);
		
		$fields->removeByName('Filename');
		$fields->removeByName('Name');
		$fields->removeByName('Content');
		$fields->removeByName('Sort');
		$fields->removeByName('Parent');
		$fields->removeByName('ShowInSearch');
		$fields->removeByName('Main');
		$fields->removeByName('BackLinkTracking');
		
		//gallery - unsure what to do with these just yet
		$fields->removeByName('GalleryID');
		$fields->removeByName('GalleryClassName');
		
		return $fields;
	}
	
	/**
	 * GetFileIcon()
	 * @returns string
	 */
	public static function GetFileIcon($type = "") {
		return "<img src=\"" . SAPPHIRE_DIR . "/images/app_icons/generic_32.gif\" width=\"24\" height=\"32\" alt=\"file icon\" />";
	}
	
}
?>