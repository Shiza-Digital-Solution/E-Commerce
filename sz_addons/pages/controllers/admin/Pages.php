<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Pages extends CI_Controller{ 

	private $moduleName = '';

	public function __construct(){
		parent::__construct();
		$this->load->helper('admin_functions');

		// protect the page
		$this->adminauth->auth_login();

		// define module name variable
		$this->moduleName = t( array('table'=>'users_menu', 'field'=>'menuName', 'id'=> 36) );

		// load model
		$this->load->model('pages_model');
	}

	public function index(){
		if( is_view() ){
			$datapage = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;

			$clausequery = '';
			if( $this->input->get('postdisplay')=='draft' ){
				$clausequery = " AND contentStatus='0'";
			}

			$table = 'contents';
			$where = "contentType='page'".$clausequery;

			$excClause = '';

            if(!empty($this->input->get('kw'))){
				$kw = $this->security->xss_clean( $this->input->get('kw') );

				$queryserach = "contentTitle LIKE '%{$kw}%'";
				$excClause = " AND ( $queryserach )";
				
				// check multilanguage
				$lang = get_cookie('admin_lang');
				if( $lang != $this->config->item('language') ){
					// check the keyword here
					$dataidresult = $this->Env_model->view_where("dtRelatedId","dynamic_translations","dtRelatedTable='{$table}' AND dtLang='{$lang}' AND ( dtRelatedId IN (SELECT contentId FROM ".$this->db->dbprefix($table)." WHERE contentId=dtRelatedId AND contentType='page') AND (dtRelatedField='contentTitle' AND dtTranslation LIKE '%{$kw}%') ) ");

					$standardlangcount = countdata($table, $where . $excClause);

					if( count($dataidresult)>0 ){
						$resultlangsearch = array();
						foreach($dataidresult AS $key => $val){
							$resultlangsearch[] = $val['dtRelatedId'];
						}

						$querysearchlang = ($standardlangcount > 0) ? '(':'';
						$querysearchlang .= '( contentId=\'' .implode('\' OR contentId=\'', $resultlangsearch). '\' )';

						if( $standardlangcount > 0 ){
							$querysearchlang .= " OR (".$queryserach.")";
						}

						$querysearchlang .= ($standardlangcount > 0) ? ')':'';

						$excClause = " AND $querysearchlang";
						
					} else {
						if($standardlangcount < 1){
							$excClause = " AND contentTitle=''";
						}
					}
				}
            }

			$perPage = 30;

			$where = $where.$excClause;
			$datauser = $this->Env_model->view_where_order_limit('*', $table, $where, 'contentId', 'DESC', $perPage, $datapage);

			$rows = countdata($table, $where);
			$pagingURI = admin_url( $this->uri->segment(2) );

			$this->load->library('paging');
			$pagination = $this->paging->PaginationAdmin( $pagingURI, $rows, $perPage );

			$data = array( 
						'title' => $this->moduleName . ' - '.get_option('sitename'),
						'page_header_on' => true,
						'title_page' =>  $this->moduleName,
						'title_page_icon' => '',
						'title_page_secondary' => '',
						'breadcrumb' => false,
						'header_button_action' => array(
											array(
												'title' => t('addnew'),
												'icon'	=> 'fe fe-plus',
												'access' => admin_url('pages/addnew'),
												'permission' => 'add'
											)
										),
						'data' => $datauser,
						'pagination' => $pagination,
						'totaldata' => $rows
					);
			
			$this->load->view( admin_root('pages_view'), $data );
		}
	}

	public function addnew(){
		if( is_add() ){
			$data = array( 
							'title' => $this->moduleName . ' - '.get_option('sitename'),
							'page_header_on' => true,
							'title_page' => $this->moduleName . ' - ' . t('addnew'),
							'title_page_icon' => '',
							'title_page_secondary' => '',
							'breadcrumb' => false,
							'header_button_action' => array(
												array(
													'title' => t('back'),
													'icon'	=> 'fe fe-corner-up-left',
													'access' => admin_url('pages'),
													'permission' => 'view'
												)
											),
						);

			$this->load->view( admin_root('pages_add'), $data );
		}
	}

	public function addprocess(){
		if( is_add()){
			$error = false;

			if(empty($this->input->post('title')) OR empty($this->input->post('desc')) ){
				$error = "<strong>".t('error')."!!</strong> ".t('emptyrequiredfield');
			}

			// file extention allowed
			$extensi_allowed = array('jpg','jpeg','png');

			// check image upload
			if(!empty($_FILES['picture']['tmp_name'])){
				$ext_file = strtolower(pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION));

				if(!in_array($ext_file,$extensi_allowed)) {
					$error = "<strong>".t('error')."!!</strong> " . t('wrongextentionfile');
				}
			}

			if(!$error){
				$title 		= esc_sql( filter_txt( $this->input->post('title') ) );
				$desc 		= filter_editor( $this->input->post('desc') );
				$draftstatus = ($this->input->post('draftstatus')==1)?0:1;
				$imgcaption	= esc_sql( filter_txt( $this->input->post('imgcaption') ) );

				// get next Id
				$nextId = getNextId('contentId', 'contents');

				// get user data
				$user = $this->session->userdata('username');
				$userid = $this->session->userdata('adminid');
				$getuserdata = getval('*', 'users', array('userId'=>$userid));

				// get timestamp now
				$now = time2timestamp();

				// upload image proccess
				$file_img = '';
				$file_dir = '';
				if(!empty($_FILES['picture']['tmp_name'])){
					$ext_file = strtolower(pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION));

					if(in_array($ext_file,$extensi_allowed)) {
						$sizeimg = array(
							'xsmall' 	=>'90',
							'small' 	=>'210',
							'medium' 	=>'530',
							'large' 	=>'980',
							'xlarge' 	=>'1620'
						);
						$img = uploadImage('picture', 'page', $sizeimg, $extensi_allowed);
						$file_img = $img['filename'];
						$file_dir = $img['directory'];
					}
				}

				$slug = slugURL($title);

				$data = array(
					'contentId' => $nextId,
					'contentUsername' => $user,
					'contentTitle' => $title,
					'contentPost' => $desc,
					'contentType' => 'page',
					'contentDd' => date('d'),
					'contentMm' => date('m'),
					'contentYy' =>  date('Y'),
					'contentDate' => date('Y-m-d'),
					'contentHour' => date('H:i:s'),
					'contentTimestamp' => $now,
					'contentDatetime' => timestamp2time($now),
					'contentAddDate' => $now,
					'contentSlug'=>(string) $slug,
					'contentRead' => 0,
					'contentCommentStatus' => 0,
					'contentStatus' => $draftstatus,
					'contentEditor' => '',
					'contentAuthor' => $getuserdata['userDisplayName'],
					'contentImg' => (string) $file_img,
					'contentDirImg' => (string) $file_dir,
					'contentCaptionImg' => (string) $imgcaption,
					'contentHeadline' => '',
					'contentFeature' => '',
				);
				$query = $this->Env_model->insert('contents', $data);

				if($query){

					translate_pushdata('title', 'contents', 'contentTitle', $nextId );
					translate_pushdata('desc', 'contents', 'contentPost', $nextId );
					translate_pushdata('imgcaption', 'contents', 'contentCaptionImg', $nextId );

					// insert seo data
					if(!empty($this->input->post('seo_judul')) OR !empty($this->input->post('seo_deskripsi')) OR !empty($this->input->post('kw')) OR !empty($this->input->post('noindex')) OR !empty($this->input->post('nofollow'))){
						setSeoContent(
							'insert',
							'page',
							$nextId,
							$this->input->post('seo_judul', true),
							$this->input->post('seo_deskripsi', true),
							$this->input->post('kw', true),
							$this->input->post('noindex', true),
							$this->input->post('nofollow', true)
						);
					}

					$this->session->set_flashdata( 'succeed', t('successfullyadd'));
					redirect( admin_url('pages/edit/'.$nextId) ); exit;

				} else {
			    	$this->session->set_flashdata( 'failed', t('cannotprocessdata') );
				}
			}

			if($error){
				$this->session->set_flashdata( 'failed', $error );
			}

			redirect( admin_url('pages/addnew') );
		}
	}

	public function edit($id){
		if( is_edit() ){

			$id = esc_sql(filter_int($id));

			// get data
			$data = getval('*','contents', array('contentId'=>$id));

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
													'access' => admin_url('pages/addnew'),
													'permission' => 'add'
												),
												array(
													'title' => t('back'),
													'icon'	=> 'fe fe-corner-up-left',
													'access' => admin_url('pages'),
													'permission' => 'view'
												)
											),
							'data' => $data,
						);

			$this->load->view( admin_root('pages_edit'), $data );
		}
	}

	public function editprocess(){
		if( is_edit() ){
			$error = false;

			$id = esc_sql(filter_int( $this->input->post('ID',true) ));

			if(empty($this->input->post('addeddate')) OR empty($this->input->post('addedtime')) OR empty($this->input->post('title')) OR empty($this->input->post('desc')) ){
				$error = "<strong>".t('error')."!!</strong> ".t('emptyrequiredfield');
			}

			// file extention allowed
			$extensi_allowed = array('jpg','jpeg','png');

			// check image upload
			if(!empty($_FILES['picture']['tmp_name'])){
				$ext_file = strtolower(pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION));

				if(!in_array($ext_file,$extensi_allowed)) {
					$error = "<strong>".t('error')."!!</strong> " . t('wrongextentionfile');
				}
			}

			if(!$error){
				$title 		= esc_sql( filter_txt( $this->input->post('title') ) );
				$desc 		= filter_editor( $this->input->post('desc') );
				$draftstatus = ($this->input->post('draftstatus')==1)?0:1;
				$imgcaption	= esc_sql( filter_txt( $this->input->post('imgcaption') ) );
				$addeddate	= esc_sql( filter_txt( $this->input->post('addeddate') ) );
				$addedtime	= esc_sql( filter_txt( $this->input->post('addedtime') ) );

				// get user data
				// $user = $this->session->userdata('username');
				// $userid = $this->session->userdata('adminid');
				// $getuserdata = getval('*', 'users', array('userId'=>$userid));

				// set date and time
				$now = time2timestamp();
				$exp_date = explode('-', $addeddate);
				$dd = $exp_date[0];
				$mm = $exp_date[1];
				$yy = $exp_date[2];
				$ymd = $exp_date[2].'-'.$exp_date[1].'-'.$exp_date[0];
				$his = $addedtime.':00';
				$timestamp = time2timestamp( $ymd .' ' .$his);
				$his = $addedtime.':00';

				// upload image proccess
				$file = array();

				// upload image proccess
				if(!empty($_FILES['picture']['tmp_name'])){

					$ext_file = strtolower(pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION));

					if(in_array($ext_file,$extensi_allowed)) {
						$sizeimg = array(
							'xsmall' 	=>'90',
							'small' 	=>'210',
							'medium' 	=>'530',
							'large' 	=>'980',
							'xlarge' 	=>'1620'
						);

						$dataimg = getval("*", 'contents', "contentId='{$id}'" );
						if(!empty($dataimg['contentImg']) AND !empty($dataimg['contentDirImg'])){
							
							//delete old file
							foreach($sizeimg AS $imgkey => $valimg){
								@unlink( IMAGES_PATH . DIRECTORY_SEPARATOR .$dataimg['contentDirImg'].DIRECTORY_SEPARATOR.$imgkey.'_'.$dataimg['contentImg']);
							}
							
						}

						$img = uploadImage('picture', 'page', $sizeimg, $extensi_allowed);
						$file_img = $img['filename'];
						$file_dir = $img['directory'];
						
						$file = array( 'contentDirImg'=> $file_dir, 'contentImg'=>$file_img );
					}
				}

				// set slug
				$slug	= slugURL( esc_sql( filter_txt( $this->input->post('postslug') ) ) );
				if( $this->input->post('postslug')==null){
					$slug = slugURL($title);
				}

				$data = array(
					'contentTitle' => $title,
					'contentPost' => $desc,
					'contentDd' => $dd,
					'contentMm' => $mm,
					'contentYy' =>  $yy,
					'contentDate' => $ymd,
					'contentHour' => $his,
					'contentTimestamp' => $timestamp,
					'contentDatetime' => timestamp2time($timestamp),
					'contentSlug' => (string) $slug,
					'contentCommentStatus' => 0,
					'contentStatus' => $draftstatus,
					'contentEditor' => '',
					'contentCaptionImg' => (string) $imgcaption,
					'contentHeadline' => '',
					'contentFeature' => '',
				);

				$data = array_merge($data, $file);

				$query = $this->Env_model->update('contents', $data, array('contentId'=>$id));

				if($query){

					translate_pushdata('title', 'contents', 'contentTitle', $id );
					translate_pushdata('desc', 'contents', 'contentPost', $id );
					translate_pushdata('imgcaption', 'contents', 'contentCaptionImg', $id );

					// insert seo data
					if(!empty($this->input->post('seo_judul')) OR !empty($this->input->post('seo_deskripsi')) OR !empty($this->input->post('kw')) OR !empty($this->input->post('noindex')) OR !empty($this->input->post('nofollow'))){
						setSeoContent(
							'update',
							'page',
							$id,
							$this->input->post('seo_judul', true),
							$this->input->post('seo_deskripsi', true),
							$this->input->post('kw', true),
							$this->input->post('noindex', true),
							$this->input->post('nofollow', true)
						);
					}

					$this->session->set_flashdata( 'succeed', t('successfullyadd'));
					redirect( admin_url('pages/edit/'.$id) ); exit;

				} else {
			    	$this->session->set_flashdata( 'failed', t('cannotprocessdata') );
				}
			}

			if($error){
				$this->session->set_flashdata( 'failed', $error );
			}

			redirect( admin_url('pages/edit/'.$id) );
		}
	}

	protected function deleteAction($id){
		if( is_delete() ){
			$id = esc_sql( filter_int( $id ) );
			
			$dataimg = getval("contentImg,contentDirImg", 'contents', "contentId='{$id}'" );
			if(!empty($dataimg['contentDirImg']) AND !empty($dataimg['contentImg'])){
				$sizeimg = array(
					'xsmall' 	=>'90',
					'small' 	=>'210',
					'medium' 	=>'530',
					'large' 	=>'980',
					'xlarge' 	=>'1620'
				);

				//delete old file
				foreach($sizeimg AS $imgkey => $valimg){
					@unlink( IMAGES_PATH . DIRECTORY_SEPARATOR .$dataimg['contentDirImg'].DIRECTORY_SEPARATOR.$imgkey.'_'.$dataimg['contentImg']);
				}
			}

			$where = array('contentId' => $id, 'contentType' => 'page');
			$query = $this->Env_model->delete('contents', $where);
			if($query){
				// remove translate
				translate_removedata('contents', $id );
				
				return true;
			} else {
				return false;
			}
		}
	}
	public function delete($id){
		if( is_delete() ){
			$query = $this->deleteAction($id);
			if($query){

				$this->session->set_flashdata( 'succeed', t('successfullydeleted') );

			} else {

				$this->session->set_flashdata( 'failed', t('cannotprocessdata') );

			}

			redirect( admin_url('pages') );
		}
	}

	public function bulk_action(){
		$error = false;
		if(empty($this->input->post('bulktype'))){
			$error = "<strong>".t('error')."!!</strong> ". t('bulkactionnotselectedyet');
		}

		if(!$error){
			if( $this->input->post('bulktype')=='bulk_delete' AND is_delete() ){
				$theitem = (!empty($this->input->post('item'))) ? array_filter($this->input->post('item')):array();

				if( count($theitem) > 0 ){
					$stat_hapus = FALSE;

					foreach ($theitem as $key => $value) {
						if($value == 'y'){
							$id = filter_int($this->input->post('item_val')[$key]);

							$queryact = $this->deleteAction($id);

							if($queryact){

								$stat_hapus = TRUE;

							} else {

								$stat_hapus = FALSE; break;

							}
						}
					}

					if($stat_hapus){
						$this->session->set_flashdata( 'succeed', t('successfullydeleted') );
					} else {
					  	$this->session->set_flashdata( 'failed', t('cannotprocessdata') );
					}

					redirect( admin_url('pages') );
					exit;

				} else {
					$error = "<strong>".t('error')."</strong>".t('bulkactionnotselecteditemyet');
				}

			}
			redirect( admin_url('pages') );
		}

		if($error){
			show_error($error, 503,t('actionfailed'));
			exit;
		}
	}

}
