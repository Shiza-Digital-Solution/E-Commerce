<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Website_menu extends CI_Controller{ 

	private $moduleName = '';

	public function __construct(){
		parent::__construct();
		// load helper required
		$this->load->helper('cookie');
		$this->load->helper('admin_functions');

		// protect the page
		$this->adminauth->auth_login();

		// define module name variable
		$this->moduleName = t( array('table'=>'users_menu', 'field'=>'menuName', 'id'=> 30) );

		// load model
		$this->load->model('website_menu_model');
	}

	protected function drawMenu($groupId, $listParentMenu = null, $parentnumberlist = null, $submenulevel = 1){
		$result = '';

		// check sub menu
		if($submenulevel <= WEBMENUDEEPLIMIT){

			$tablemenu = array('website_menu a', 'category_relationship b');

			$where = '';
			if($listParentMenu == null){
				$where .= "a.menuParentId = '0' AND ";
			} else {
				$where .= "a.menuParentId = '{$listParentMenu}' AND a.menuParentId != '0' AND ";
			}

			$where .= "b.relatedId = a.menuId AND b.crelRelatedType = 'webmenu' AND b.catId = '{$groupId}' AND a.storeId='".storeId()."'";

			$countmenuavailabel = countdata($tablemenu, $where);

			if($countmenuavailabel > 0){

				$menudata = $this->Env_model->view_where_order("a.*", $tablemenu, $where,'a.menuSort','ASC');

				$xx1 = 1;
				foreach ($menudata AS $pm1) {

					$result .= "<option value=\"{$submenulevel}-{$pm1['menuId']}\">";

					if($submenulevel != 1){
						$subloop = $submenulevel-1;
						for($sbmn = 1; $sbmn <= $subloop; $sbmn++){
							for($x = 1; $x <= 5; $x++){
								$result .= "&nbsp;";
							}
						}
					}

					for($sx = 1; $sx < $submenulevel; $sx++){
						$result .= ($parentnumberlist!=null )?$parentnumberlist.'.':'';
					}

					$arraytranslate = array('table'=>'website_menu','field'=>'menuName','id'=>$pm1['menuId']);

					$result .= "{$xx1}. ".t($arraytranslate)."</option>"."\n";

					$nextsubmenu = $submenulevel+1;

					// recursive menu
					$result .= $this->drawMenu($groupId, $pm1['menuId'], $xx1, $nextsubmenu);
					
					$xx1++;

				}

			}
		}

		return $result;
	}

	public function index(){
		if( is_view() ){

			// get menu category
			$datamncat = $this->Env_model->view_where_order('catId,catName,catDesc','categories',array('catActive'=>'1','catType'=>'webmenu'),'catId','DESC');
			$groupmenu = $this->input->get('groupmenu');

			$catmenuselected = '';
			$catmenu = array();
			foreach( $datamncat as $v ){
				if( !empty($groupmenu) ){
					if( $groupmenu == $v['catId'] ){
						$catmenuselected = $v['catId'];
					}
				} else {
					if($v['catDesc']=='primary'){
						$catmenuselected = $v['catId'];
					}
				}

				$catmenu[$v['catId']] = $v['catName'] . ( ($v['catDesc']=='primary') ?' ('.t('primary').')':'' );
			}

			// get menu for parent menu
			$adminmenulistopt = $this->drawMenu($catmenuselected);

			// get page data
			$datapage = $this->Env_model->view_where_order('contentId,contentTitle,contentPost','contents',array('contentStatus'=>'1','contentType'=>'page'),'contentId','DESC');

			$page = array();
			foreach( $datapage as $v ){
				$page[$v['contentId']] = t( array('table'=>'contents', 'field'=>'prodName', 'id'=>$v['prodId']) );
			}

			// get post categories
			$datapostcat = $this->Env_model->view_where_order('catId,catName','categories',array('catActive'=>'1','catType'=>'post'),'catId','DESC');

			$postcat = array();
			foreach( $datapostcat as $v ){
				$postcat[$v['catId']] = t( array('table'=>'categories', 'field'=>'catName', 'id'=>$v['catId']) );
			}

			$data = array( 
						'title' => $this->moduleName . ' - '.get_option('sitename'),
						'page_header_on' => true,
						'title_page' =>  $this->moduleName,
						'title_page_icon' => '',
						'title_page_secondary' => '',
						'breadcrumb' => false,
						'menucat' => $catmenu,
						'menuselected' => $catmenuselected,
						'optadminmenu' => $adminmenulistopt,
						'datapage' => $page,
						'datacatpost' => $postcat
					);
			
			$this->load->view( admin_root('website_menu_view'), $data );
		}
	}

	public function addgroup(){
		if( is_add() ){
			$error = false;

			if( empty( $this->input->post('titlegroup') ) ){
				$error = "<strong>".t('error')."!!</strong> ".t('emptyrequiredfield');
			}

			if(!$error){
				$nama 		= esc_sql( filter_txt( $this->input->post('titlegroup') ) );
				
				$nextId = getNextId('catId', 'categories');

				$array_ins = array(
					'catId' => $nextId,
					'catName' => $nama,
					'catSlug'=> (string) '',
					'catColor' => (string) '',
					'catActive' => '1',
					'catType' => 'webmenu',
				);

				$countgroup = countdata("categories","catType='webmenu'");
				if( $countgroup < 1){
					$dataprimary = array('catDesc' => 'primary');
				} else {
					$dataprimary = array('catDesc' =>  (string) '');
				}
				$array_ins = array_merge($array_ins, $dataprimary);
				
				// insert data
				$query = $this->Env_model->insert('categories', $array_ins);
				
			    if($query){

					// add store
					$datastore = array(
						'catId' => $nextId,
						'storeId' => storeId()
					);
					$query = $this->Env_model->insert('category_store', $datastore);

					$this->session->set_flashdata( 'succeed', t('successfullyadd'));
					redirect( admin_url('website_menu/?groupmenu='.$nextId) ); exit;
			    } else {
			    	$this->session->set_flashdata( 'failed', t('cannotprocessdata') );
				}
			}

			if($error){
				$this->session->set_flashdata( 'failed', $error );
			}

			redirect( admin_url('website_menu') );
		}
	}

	public function editgroup(){
		if( is_edit() ){
			$error = false;

			if( empty( $this->input->post('titlegroup') ) ){
				$error = "<strong>".t('error')."!!</strong> ".t('emptyrequiredfield');
			}

			if(!$error){
				$nama = esc_sql( filter_txt( $this->input->post('titlegroup') ) );
				$id   = esc_sql( filter_int( $this->input->post('idgroup') ) );

				$dataupdate = array(
					'catName' => $nama,
				);

				if( !empty( $this->input->post('primary') ) ){
					if($this->input->post('primary') == 'y'){
						// update primary menu first
						$this->Env_model->update("categories",array('catDesc' => (string) ''),"catType='webmenu'");
		
						$dataprimary = array('catDesc' => 'primary');
						$dataupdate = array_merge($dataupdate, $dataprimary);
					}
				}
				
				// insert data
				$query = $this->Env_model->update('categories', $dataupdate, array('catId'=>$id,'catType'=>'webmenu'));
				
			    if($query){
					$this->session->set_flashdata( 'succeed', t('successfullyadd'));
			    } else {
			    	$this->session->set_flashdata( 'failed', t('cannotprocessdata') );
				}
			}

			if($error){
				$this->session->set_flashdata( 'failed', $error );
			}

			redirect( admin_url('website_menu/?groupmenu='.$id) );
			
		}
	}

	public function addnew(){
		if( is_add() ){
			$error = false;
			$groupId = esc_sql( filter_int( $this->input->post('groupmenu') ) );

			if(empty($this->input->post('nama_menu'))){
				$error = "<strong>".t('error')."!!</strong> ".t('emptyrequiredfield');
			}
		
			if($this->input->post('menu_akses')=='pagecontent_link' AND empty($this->input->post('pagecontent'))){
				$error = "<strong>".t('error')."!!</strong> ".t('pagefieldempty');
			}
		
			if($this->input->post('menu_akses')=='newscategory_link' AND empty($this->input->post('newscategory'))){
				$error = "<strong>".t('error')."!!</strong> ".t('categoryfieldempty');
			}
		
			if($this->input->post('menu_akses')=='outgoing_link' AND empty($this->input->post('outgoinglink'))){
				$error = "<strong>".t('error')."!!</strong> ".t('externallinkfieldempty');
			}
		
			$exp_induk = explode("-",$this->input->post('induk'));
			$levelberikut=$exp_induk[0]+1;
		
			if($levelberikut > WEBMENUDEEPLIMIT ){
				$error = "<strong>".t('error')."</strong> ".t('deepmenuerror');
			}
		
			//insert data
			if(!$error){
				$menuName   = esc_sql( filter_txt( $this->input->post('nama_menu') ) );
				$attrclass  = esc_sql( filter_txt( $this->input->post('attrclass') ) );
				$groupmenu  = esc_sql( filter_int( $this->input->post('groupmenu') ) );
				$menuAccess	= $this->input->post('menu_akses');

				$relID = 0;

				if($menuAccess=='pagecontent_link'){
					$pageid = esc_sql( filter_int( $this->input->post('pagecontent') ) );
					$val = getval("contentSlug,contentId","contents","contentId='{$pageid}'");

					$dataAccess = "{HOME_URL}/page/".$val['contentId']."/". $val['contentSlug'].".html";
					$relID = $pageid;
				} elseif($menuAccess=='newscategory_link'){
					$catid = esc_sql( filter_int( $this->input->post('newscategory') ) );
					$val = getval("catSlug,catId","categories","catId='{$catid}'");

					$dataAccess = "{HOME_URL}/category/".$val['catId']."/" . $val['catSlug'].".html";
					$relID = $catid;
				} elseif($menuAccess=='outgoing_link'){
					$outgoinglink  = filter_txt( $this->input->post('outgoinglink', true) );

					$baseurislash = ( substr(base_url(), -1) == '/' ) ? base_url():base_url().'/';
					$outgoinglinkslash = ( substr($outgoinglink, -1) == '/' ) ? $outgoinglink:$outgoinglink.'/';
					
					if($outgoinglink == '/' OR $outgoinglinkslash == $baseurislash ){
						$theURL = "{HOME_URL}";
					} else {
						$httpmodel = ( is_https() OR $_SERVER['SERVER_PORT'] == 443 ) ? "https://" : "http://";
						$cuthttpreferrer = str_replace( $httpmodel,'',substr(base_url(), 0, -1) );

						if( strpos($outgoinglink, $cuthttpreferrer) ){
							// convert domain to variable
							$parsed_url=parse_url($outgoinglink);

							$port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : ''; 
							$path     = isset($parsed_url['path']) ? $parsed_url['path'] : ''; 
							$query    = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : ''; 
							$fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';

							$theURL = "{HOME_URL}".$port.$path.$query.$fragment;
						} else {
							$theURL = $outgoinglink;
						}
					}
					$dataAccess = $theURL;
					$relID = 0;
				} elseif($menuAccess=='no_link'){
					$dataAccess = "";
					$relID = 0;
				}

				$addDate = time2timestamp();

				$nextsort = nextSort("website_menu","menuSort","menuParentId='{$exp_induk[1]}'");

				$nextID = getNextId('menuId','website_menu');

				$array_ins = array(
							'menuId'			=> $nextID,
							'storeId'			=> storeId(),
							'menuParentId'		=> $exp_induk[1],
							'menuRelationshipId'=> $relID, 
							'menuName'			=> $menuName, 
							'menuAccessType'	=> $menuAccess, 
							'menuUrlAccess'		=> (string) $dataAccess, 
							'menuAddedDate'		=> $addDate, 
							'menuSort'			=> $nextsort, 
							'menuActive'		=> 'y',
							'menuAttrClass'     => (string) $attrclass
						);
				
				$queryins = $this->Env_model->insert("website_menu",$array_ins);

				if($queryins){
					// insert or update data translation
					translate_pushdata('nama_menu', 'website_menu', 'menuName', $nextID );

					$nextIDkat = getNextId('crelId','category_relationship');
					$array_ins_kat = array(
							'crelId'      => $nextIDkat,
							'catId'     => $groupId,
							'relatedId'  => $nextID, 
							'crelRelatedType'    => 'webmenu'
						);				
					$this->Env_model->insert("category_relationship",$array_ins_kat);

					$this->session->set_flashdata( 'succeed', t('successfullyadd'));
			    } else {
			    	$this->session->set_flashdata( 'failed', t('cannotprocessdata') );
				}
			}

			if($error){
				$this->session->set_flashdata( 'failed', $error );
			}

			redirect( admin_url('website_menu/?groupmenu='.$groupId) );
		}
	}

	public function edit($id){
		if( is_edit() ){
			$data = array( 
							'title' => $this->moduleName . ' - '.get_option('sitename'),
							'page_header_on' => true,
							'title_page' => $this->moduleName . ' - ' . t('edit'),
							'title_page_icon' => '',
							'title_page_secondary' => '',
							'breadcrumb' => false,
							'header_button_action' => array(
												array(
													'title' => t('addnew'),
													'icon'	=> 'fe fe-plus',
													'access' => admin_url('website_menu/addnew'),
													'permission' => 'add'
												),
												array(
													'title' => t('back'),
													'icon'	=> 'fe fe-corner-up-left',
													'access' => admin_url('website_menu'),
													'permission' => 'view'
												)
											),
						);

			$this->load->view( admin_root('website_menu_edit'), $data );
		}
	}

	protected function deleteGroupAction($id){
		if( is_delete() ){
			$id = esc_sql( filter_int($id) );
			$result = false;

			// check if category group is primary menu
			if( countdata('categories', "catId='{$id}' AND catDesc!='primary'" ) > 0 ){
				$dcatrel = $this->Env_model->view_where("*", 'category_relationship', "catId='{$id}' AND crelRelatedType='webmenu'" );
				foreach($dcatrel as $datarel){
					// remove menu first
					$this->Env_model->delete('website_menu', array('menuId'=>$datarel['relatedId']));
				}

				// and then remove relastionship data
				$delete = $this->Env_model->delete('category_relationship', array('catId'=>$id, 'crelRelatedType'=> 'webmenu'));

				if($delete){
					// remove category
					$this->Env_model->delete('categories', array('catId'=>$id, 'catType'=> 'webmenu'));

					// remove category store
					$this->Env_model->delete('category_store', array('catId'=>$id, 'storeId'=>storeId()));

					$result = true;
				}

			}

			return $result;
			
		}
	}
	public function deletegroup($id){
		if( is_delete() ){
			
			$update = $this->deleteGroupAction($id);

			if( $update ){

				$this->session->set_flashdata( 'succeed', t('successfullydeleted') );

			} else {

				$this->session->set_flashdata( 'failed', t('cannotprocessdata') );

			}
			redirect( admin_url('website_menu') );
		}
	}

}
