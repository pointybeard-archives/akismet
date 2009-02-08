<?php

	Class extension_akismet extends Extension{

		public function about(){
			return array('name' => 'Akismet Spam Filtering',
						 'version' => '1.3',
						 'release-date' => '2009-02-08',
						 'author' => array('name' => 'Alistair Kearney',
										   'website' => 'http://www.pointybeard.com',
										   'email' => 'alistair@pointybeard.com'),
						 'description' => 'Allows you to add a spam filter to your front end saving events.'
				 		);
		}
		
		public function getSubscribedDelegates(){
			return array(
						array(
							'page' => '/blueprints/events/new/',
							'delegate' => 'AppendEventFilter',
							'callback' => 'addFilterToEventEditor'
						),
						
						array(
							'page' => '/blueprints/events/edit/',
							'delegate' => 'AppendEventFilter',
							'callback' => 'addFilterToEventEditor'
						),
						
						array(
							'page' => '/blueprints/events/new/',
							'delegate' => 'AppendEventFilterDocumentation',
							'callback' => 'addFilterDocumentationToEvent'
						),
											
						array(
							'page' => '/blueprints/events/edit/',
							'delegate' => 'AppendEventFilterDocumentation',
							'callback' => 'addFilterDocumentationToEvent'
						),
						
						array(
							'page' => '/system/preferences/',
							'delegate' => 'AddCustomPreferenceFieldsets',
							'callback' => 'appendPreferences'
						),
						
						array(
							'page' => '/frontend/',
							'delegate' => 'EventPreSaveFilter',
							'callback' => 'processEventData'
						),						
			);
		}
		
		public function addFilterToEventEditor($context){
			$context['options'][] = array('akismet', @in_array('akismet', $context['selected']) ,'Akismet Spam Filtering');		
		}
		
		public function appendPreferences($context){
			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');
			$group->appendChild(new XMLElement('legend', 'Akismet Spam Filtering'));

			$label = Widget::Label('Wordpress API Key');
			$label->appendChild(Widget::Input('settings[akismet][api-key]', General::Sanitize($this->getWordpressApiKey())));		
			$group->appendChild($label);
			
			$group->appendChild(new XMLElement('p', 'Get a Wordpress API key from the <a href="http://wordpress.com/api-keys/">Wordpress site</a>.', array('class' => 'help')));
			
			$context['wrapper']->appendChild($group);
						
		}
		
		public function addFilterDocumentationToEvent($context){
			if(!in_array('akismet', $context['selected'])) return;
			
			$context['documentation'][] = new XMLElement('h3', 'Akismet Spam Filtering');
			
			$context['documentation'][] = new XMLElement('p', 'Each entry will be passed to the <a href="http://akismet.com/">Akismet Spam filtering service</a> before saving. Should it be deemed as spam, Symphony will terminate execution of the Event, thus preventing the entry from being saved. You will receive notification in the Event XML. <strong>Note: Be sure to set your Akismet API key in the <a href="'.URL.'/symphony/system/preferences/">Symphony Preferences</a>.</strong>');
			
			$context['documentation'][] = new XMLElement('p', 'The following is an example of the XML returned form this filter:');
			$code = '<filter type="akismet" status="passed" />
<filter type="akismet" status="failed">Author, Email and URL field mappings are required.</filter>
<filter type="akismet" status="failed">Data was identified as spam.</filter>';

			$context['documentation'][] = contentBlueprintsEvents::processDocumentationCode($code);

			$context['documentation'][] = new XMLElement('p', 'In order to provide Akismet with a correct set of data, it is required that you provide field mappings of Author, Email and URL. The value of these mappings directly point to values in the <code>fields</code> array of <code>POST</code> data. To specify a literal value, enclose the hidden fields <code>value</code> attribute in single quotes. In the following example, <code>author</code>, <code>website</code> and <code>email</code> would correspond to <code>fields[author]</code>, <code>fields[website]</code> and <code>literal@email.com</code> respectively:');
			
			$code = '<input name="akismet[author]" value="author" type="hidden" />
<input name="akismet[email]" value="\'literal@email.com\'" type="hidden" />
<input name="akismet[url]" value="website" type="hidden" />			
';
			$context['documentation'][] = contentBlueprintsEvents::processDocumentationCode($code);
			
		}
		
		public function processEventData($context){
			
			if(!in_array('akismet', $context['event']->eParamFILTERS)) return;
			
			$mapping = $_POST['akismet'];

			if(!isset($mapping['author']) || !isset($mapping['email'])){
				$context['messages'][] = array('akismet', false, 'Author and Email field mappings are required.');
				return;
			}			
			
			foreach($mapping as $key => $val){
				if(preg_match("/^'[^']+'$/", $val)) $mapping[$key] = trim($val, "'");
				else $mapping[$key] = $context['fields'][$val];
			}
			
		 	include_once(EXTENSIONS . '/akismet/lib/akismet.curl.class.php');

	        $comment = array(
	            'comment_type' => 'comment',
	            'comment_author' => $mapping['author'],
	            'comment_author_email' => $mapping['email'],
	            'comment_content' => implode($context['fields']),
	            'permalink' => URL . $_REQUEST['page']
	        );

			if(isset($mapping['url']) && strlen(trim($mapping['url'])) > 0) $comment['comment_author_url'] = $mapping['url'];

	        $akismet = new akismet($this->getWordpressApiKey(), URL);
	        if(!$akismet->error) {
	            $valid = !$akismet->is_spam($comment);
	        }
			
			$context['messages'][] = array('akismet', $valid, (!$valid ? 'Data was identified as spam.' : NULL));
			
		}
		
		public function uninstall(){
			
			if(class_exists('ConfigurationAccessor'))
				ConfigurationAccessor::remove('akismet');	
			
			else
				$this->_Parent->Configuration->remove('akismet');	
					
			$this->_Parent->saveConfig();
			
			return true;
		}

		public function getWordpressApiKey(){
			if(class_exists('ConfigurationAccessor'))
				return ConfigurationAccessor::get('api-key', 'akismet');
					
			return $this->_Parent->Configuration->get('api-key', 'akismet');
		}		
		
	}

