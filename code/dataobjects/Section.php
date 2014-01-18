<?php
class Section extends DataObject {
    
	private static $singular_name = 'Section';
	private static $plural_name = 'Sections';	

	private static $db = array(
        'Name' => 'Varchar',
		'SectionHeader' => "Enum('None, h1, h2, h3, h4, h5, h6')",
		'SectionContent' => 'HTMLText',
        'Link' => 'Varchar',
		'Template' => 'Varchar',
		'Active' => 'Boolean(1)'
    );
    
	private static $many_many = array(
		'Images' => 'Image',
    );
	
	private static $belongs_many_many = array(
		'Pages' => 'Page'
	);
	
	private static $defaults = array(
		'Template' => 'Default',
		'Active' => 1
	);

	private static $summary_fields = array( 
		'ID' => 'ID',
		'Thumbnail' => 'Thumbnail',
		'Name' => 'Name',
		'Template' => 'Template',
		'ClassName' => 'Type',
		'getIsActive' => 'Active'
	);
	
	private static $searchable_fields = array(
		'ID' => 'PartialMatchFilter',
		'Name' => 'PartialMatchFilter',
		'SectionHeader' => 'PartialMatchFilter',
	);

	
	public function getIsActive(){
		return $this->Active ? 'Yes' : 'No';
	}
	
	public function getCMSFields() {
		$fields = parent::getCMSFields();
		
		$fields->removeByName('Sort');
		$fields->removeByName('Pages');
		$fields->removeByName('Active');
		$fields->removeByName('SectionHeader');

		// If this section belongs to more than one page, show a warning
		$pcount = $this->Pages()->Count();
		if($pcount > 1) {
			$globalwarningfield = new LiteralField("IsGlobalSectionWarning", '<p class="message warning">This section is in use on '.$pcount.' pages - any changes made will also affect the section on these pages</p>');
			$fields->addFieldToTab("Root.Main", $globalwarningfield, 'Name');
			$fields->addFieldToTab("Root.Images", $globalwarningfield, 'Images');
			$fields->addFieldToTab("Root.Template", $globalwarningfield);
			$fields->addFieldToTab("Root.Settings", $globalwarningfield);
		}
		
		
		$thumbField = new UploadField('Images', 'Images');
		$thumbField->allowedExtensions = array('jpg', 'gif', 'png');
	
		$fields->addFieldsToTab("Root.Main", new TextField('Name', 'Name'));
		$fields->addFieldsToTab("Root.Main", new DropdownField('SectionHeader', 'Choose a header', $this->dbObject('SectionHeader')->enumValues()), 'SectionContent');
		$fields->addFieldsToTab("Root.Main", new HTMLEditorField('SectionContent', 'Content'));

		// Image tab
		$fields->addFieldsToTab("Root.Images", $thumbField);
		
		
		// Template tab
		$optionset = array();
		$theme = SSViewer::current_theme();
		$src    = "../themes/".$theme."/templates/SectionTemplates/";
		
		if(file_exists($src)) {
			foreach (glob($src . "*.ss") as $filename) {	
				$name = $this->file_ext_strip(basename($filename));
				
				// Is there a template thumbnail
				
				$thumbnail = (file_exists($src . $name . '.png') ? '<img src="' .$src . $name . '.png" />' :  '<img src="' .$src . 'Blank.png" />');				
				$html = '<div class="sectionThumbnail">'.$thumbnail.'</div><strong class="title" title="Template file: '.$filename.'">'. $name .'</strong>';
				$optionset[$name] = $html;
			}
			
			$tplField = new OptionsetField(
				"Template", 
				"Choose a template", 
				$optionset, 
				$this->Template
			);
			$fields->addFieldsToTab("Root.Template", $tplField);
		} else {
			$fields->addFieldsToTab("Root.Template", new LiteralField ($name = "literalfield", $content = '<p class="message warning"><strong>Warning:</strong> The folder '.$src.' was not found.</div>'));
		}

		// Settings tab
		$fields->addFieldsToTab("Root.Settings", new CheckboxField('Active', 'Active'));
		$fields->addFieldsToTab("Root.Settings", new TextField('Link', 'Link'));
		
		// Moving a section from one page to another - not working
		$fields->addFieldsToTab("Root.Settings", new TreeDropdownField("MoveTo", "Move this section to:", "SiteTree"));		

		$PagesConfig = GridFieldConfig_RelationEditor::create(10);
		$PagesConfig->removeComponentsByType('GridFieldAddNewButton');
		$gridField = new GridField("Pages", "Related pages (This section is used on the following pages)", $this->Pages(), $PagesConfig);
		
		$fields->addFieldToTab("Root.Settings", $gridField);
		
		return $fields;
	}	

	function onBeforeWrite() {
		parent::onBeforeWrite();
					
		// Moving a section from one page to another - not working
		// Left over from has_many version
		//if($this->MoveTo) {
			//$this->PageID = $this->MoveTo;
		//}
		
	}

	function requireDefaultRecords() {
		parent::requireDefaultRecords();
		// Run on dev buld		
		
		// If templates does not exist on current theme, copy from module
		$theme = SSViewer::current_theme();
		$copyto    = "../themes/".$theme."/templates/SectionTemplates/";
		
		if(!file_exists($copyto)) {
			$copyfrom = BASE_PATH . "/sectionmodule/templates/SectionTemplates/";
			if(file_exists($copyfrom)) {
				$this->recurse_copy($copyfrom, $copyto);
				echo '<li style="color: green">SectionTemplates copied to: '.$copyto.'</li>';
			} else {
				echo "The default template archive was not found: " . $copyfrom;
			}
		}
	}	

	public function canDelete($member = null) {
		if(!$member || !(is_a($member, 'Member')) || is_numeric($member)) $member = Member::currentUser();

		// extended access checks
		$results = $this->extend('canDelete', $member);
		
		if($results && is_array($results)) {
			if(!min($results)) return false;
			else return true;
		}

		// No member found
		if(!($member && $member->exists())) return false;
		
		$pcount = $this->Pages()->Count();
		if($pcount > 1) {
			return false;
		} else {
			return true;
		}
		
		
		return $this->canEdit($member);
	}

	function recurse_copy($src,$dst) {
		$dir = opendir($src);
		@mkdir($dst);
		while(false !== ( $file = readdir($dir)) ) {
			if (( $file != '.' ) && ( $file != '..' )) {
				if ( is_dir($src . '/' . $file) ) {
					$this->recurse_copy($src . '/' . $file,$dst . '/' . $file);
				}
				else {
					copy($src . '/' . $file,$dst . '/' . $file);
				}
			}
		}
		closedir($dir);
	}
	
	public function getThumbnail() { 
		if ($this->Images()->Count() >= 1) {
			return $this->Images()->First()->croppedImage(50,40);
		}
	}	
	
	function forTemplate() {
		return $this->renderWith($this->Template);
	}

	// Returns only the file extension (without the period).
	function file_ext($filename) {
		if( !preg_match('/\./', $filename) ) return '';
		return preg_replace('/^.*\./', '', $filename);
	}
	
	// Returns the file name, less the extension.
	function file_ext_strip($filename){
		return preg_replace('/\.[^.]*$/', '', $filename);
	}	
}