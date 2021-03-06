<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Product_categories extends CI_Controller{ 

	public function __construct(){
		parent::__construct();
		$this->load->helper('admin_functions');

		// protect the page
		$this->adminauth->auth_login();

		// load model
		$this->load->model('product_categories_model');
	}

	public function index(){
		if( is_view() ){

			$datapage = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;

			$table = 'categories';
			$where = "catType='product'";

			$excClause = '';

            if(!empty($this->input->get('kw'))){
				$kw = $this->security->xss_clean( $this->input->get('kw') );

				$queryserach = "catName LIKE '%{$kw}%' OR catDesc LIKE '%{$kw}%'";
				$excClause = " AND ( $queryserach )";
				
				// check multilanguage
				$lang = get_cookie('admin_lang');
				if( $lang != $this->config->item('language') ){
					// check the keyword here
					$dataidresult = $this->Env_model->view_where("dtRelatedId","dynamic_translations","dtRelatedTable='{$table}' AND dtLang='{$lang}' AND ( dtRelatedId IN (SELECT catId FROM ".$this->db->dbprefix($table)." WHERE catId=dtRelatedId AND catType='product') AND (dtRelatedField='catName' AND dtTranslation LIKE '%{$kw}%') OR (dtRelatedField='catDesc' AND dtTranslation LIKE '%{$kw}%') ) ");

					$standardlangcount = countdata($table, $where . $excClause);

					if( count($dataidresult)>0 ){
						$resultlangsearch = array();
						foreach($dataidresult AS $key => $val){
							$resultlangsearch[] = $val['dtRelatedId'];
						}

						$querysearchlang = ($standardlangcount > 0) ? '(':'';
						$querysearchlang .= '( catId=\'' .implode('\' OR catId=\'', $resultlangsearch). '\' )';

						if( $standardlangcount > 0 ){
							$querysearchlang .= " OR (".$queryserach.")";
						}

						$querysearchlang .= ($standardlangcount > 0) ? ')':'';

						$excClause = " AND $querysearchlang";
						
					} else {
						if($standardlangcount < 1){
							$excClause = " AND catName='' AND catDesc=''";
						}
					}
				}
            }

			$perPage = 30;

			$where = $where.$excClause;
			$datauser = $this->Env_model->view_where_order_limit('*', $table, $where, 'catId', 'DESC', $perPage, $datapage);

			$rows = countdata($table, $where);
			$pagingURI = admin_url( $this->uri->segment(2) );

			$this->load->library('paging');
			$pagination = $this->paging->PaginationAdmin( $pagingURI, $rows, $perPage );

			$data = array( 
						'title' => t('productcategories').' - '.get_option('sitename'),
						'page_header_on' => true,
						'title_page' => t('productcategories'),
						'title_page_icon' => '',
						'title_page_secondary' => '',
						'breadcrumb' => false,
						'data' => $datauser,
						'pagination' => $pagination,
						'totaldata' => $rows
					);
			
			$this->load->view( admin_root('product_categories_view'), $data );
		}
	}

	public function prosestambah(){
		if( is_add() ){
			$error = false;

			if( empty( $this->input->post('nama') ) ){
				$error = "<strong>".t('error')."!!</strong> ".t('emptyrequiredfield');
			}

			// file extention allowed
			$extensi_allowed = array('jpg','jpeg','png','gif');

			// check image upload
			if(!empty($_FILES['picture']['tmp_name'])){
				$ext_file = strtolower(pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION));

				if(!in_array($ext_file,$extensi_allowed)) {
					$error = "<strong>".t('error')."!!</strong> " . t('wrongextentionfile');
				}
			}

			if(!$error){
				$nama 		= esc_sql( filter_txt( $this->input->post('nama') ) );
				$deskripsi 	= esc_sql( filter_txt( $this->input->post('desc') ) );
				$warna 		= esc_sql( filter_txt( $this->input->post('warna') ) );
				
				$nextId = getNextId('catId', 'categories');

				$slugcat = slugURL($nama);

				$file_img = '';
				$file_dir = '';
				// upload image proccess
				if(!empty($_FILES['picture']['tmp_name'])){
					$ext_file = strtolower(pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION));

					if(in_array($ext_file,$extensi_allowed)) {
						$sizeimg = array(
							'xsmall' 	=>'90',
							'small' 	=>'210',
							'medium' 	=>'530',
							'large' 	=>'1024'
						);
						$img = uploadImage('picture', 'categories', $sizeimg, $extensi_allowed);
						$file_img = $img['filename'];
						$file_dir = $img['directory'];
					}
				}

				$datacat = array(
					'catId' => $nextId,
					'catName' => $nama,
					'catSlug'=> $slugcat,
					'catDesc' => (string) $deskripsi,
					'catColor' => (string) $warna,
					'catImgDir' => $file_dir,
					'catImg' => $file_img,
					'catActive' => 1,
					'catType' => 'product'
				);
				
				// insert data
				$query = $this->Env_model->insert('categories', $datacat);
				
			    if($query){
					// insert or update data translation
					translate_pushdata('nama', 'categories', 'catName', $nextId );
					translate_pushdata('desc', 'categories', 'catDesc', $nextId );

					// add store
					$datastore = array(
						'catId' => $nextId,
						'storeId' => storeId()
					);
					$query = $this->Env_model->insert('category_store', $datastore);

					$this->session->set_flashdata( 'succeed', t('successfullyadd'));
					redirect( admin_url('product_categories/edit/'.$nextId) ); exit;
			    } else {
			    	$this->session->set_flashdata( 'failed', t('cannotprocessdata') );
				}
			}

			if($error){
				$this->session->set_flashdata( 'failed', $error );
			}

			redirect( admin_url('product_categories') );
		}
	}

	public function edit($id){
		if( is_edit() ){
			$id = esc_sql( filter_int($id) );

			$getdata = $this->Env_model->getval("*","categories", "catId='{$id}'");

			$data = array( 
							'title' => t('productcategories') .' - '.get_option('sitename'),
							'page_header_on' => true,
							'title_page' => t('edit_category'),
							'title_page_icon' => '',
							'title_page_secondary' => '',
							'breadcrumb' => false,
							'header_button_action' => array(
												array(
													'title' => t('back'),
													'icon'	=> 'fe fe-corner-up-left',
													'access' => admin_url('product_categories'),
													'permission' => 'view'
												)
											),
							'data' => $getdata
						);

			$this->load->view( admin_root('product_categories_edit'), $data );
		}
	}

	public function prosesedit(){
		if( is_edit() ){
			$error = false;

			if( empty( $this->input->post('nama') ) OR empty( $this->input->post('slug') ) ){
				$error = "<strong>".t('error')."!!</strong> ".t('emptyrequiredfield');
			}

			// file extention allowed
			$extensi_allowed = array('jpg','jpeg','png','gif');

			// check image upload
			if(!empty($_FILES['picture']['tmp_name'])){
				$ext_file = strtolower(pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION));

				if(!in_array($ext_file,$extensi_allowed)) {
					$error = "<strong>".t('error')."!!</strong> " . t('wrongextentionfile');
				}
			}

			if(!$error){
				$id 		= esc_sql( filter_int( $this->input->post('ID') ) );

				$nama 		= esc_sql( filter_txt( $this->input->post('nama') ) );
				$slug 		= esc_sql( filter_txt( $this->input->post('slug') ) );
				$slug 		= slugURL($slug);
				$deskripsi 	= esc_sql( filter_txt( $this->input->post('desc') ) );
				$warna 		= esc_sql( filter_txt( $this->input->post('warna') ) );
				$active		= ($this->input->post('active') !==NULL) ? esc_sql( filter_txt( $this->input->post('active') ) ):0;
				
				$file = array();
				// upload image proccess
				if(!empty($_FILES['picture']['tmp_name'])){
					$ext_file = strtolower(pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION));

					if(in_array($ext_file,$extensi_allowed)) {
						$sizeimg = array(
							'xsmall' 	=>'90',
							'small' 	=>'210',
							'medium' 	=>'530',
							'large' 	=>'1920'
						);

						$dataimg = getval("*", 'categories', "catId='{$id}'" );
						if(!empty($dataimg['catImgDir']) AND !empty($dataimg['catImg'])){
							
							//delete old file
							foreach($sizeimg AS $imgkey => $valimg){
								@unlink( IMAGES_PATH . DIRECTORY_SEPARATOR .$dataimg['catImgDir'].DIRECTORY_SEPARATOR.$imgkey.'_'.$dataimg['catImg']);
							}
						}

						$img = uploadImage('picture', 'categories', $sizeimg, $extensi_allowed);
						$file_img = $img['filename'];
						$file_dir = $img['directory'];
						
						$file = array( 'catImgDir'=> $file_dir, 'catImg'=>$file_img );
					}
				}

				$datacat = array(
					'catName' => $nama,
					'catSlug'=> $slug,
					'catDesc' => (string) $deskripsi,
					'catColor' => (string) $warna,
					'catActive' => $active,
				);

				$data_ = array_merge($datacat,$file);
				
				// update data
				$query = $this->Env_model->update('categories', $data_, "catId='{$id}'");
				
			    if($query){
					// insert or update data translation
					translate_pushdata('nama', 'categories', 'catName', $id );
					translate_pushdata('desc', 'categories', 'catDesc', $id );

					$this->session->set_flashdata( 'succeed', t('successfullyupdated'));
			    } else {
			    	$this->session->set_flashdata( 'failed', t('cannotprocessdata') );
				}
			}

			if($error){
				$this->session->set_flashdata( 'failed', $error );
			}

			redirect( admin_url('product_categories/edit/'.$id) );
		}
	}

	protected function deleteAction($id){
		if( is_delete() ){
			$id = esc_sql( filter_int( $id ) );
			
			$dataimg = getval("*", 'categories', "catId='{$id}'" );
			if(!empty($dataimg['catImgDir']) AND !empty($dataimg['catImg'])){
				$sizeimg = array(
					'xsmall' 	=>'90',
					'small' 	=>'210',
					'medium' 	=>'530',
					'large' 	=>'1920'
				);

				//delete old file
				foreach($sizeimg AS $imgkey => $valimg){
					@unlink( IMAGES_PATH . DIRECTORY_SEPARATOR .$dataimg['catImgDir'].DIRECTORY_SEPARATOR.$imgkey.'_'.$dataimg['catImg']);
				}
			}

			$where = array('catId' => $id, 'catType' => 'product');
			$query = $this->Env_model->delete('categories', $where);
			if($query){
				// remove translate
				translate_removedata('categories', $id );

				// remove relationship too
				$where = array('catId' => $id, 'crelRelatedType' => 'product');
				$this->Env_model->delete('category_relationship', $where);
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

			redirect( admin_url('product_categories') );
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

							$queryact = Self::deleteAction($id);

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

					redirect( admin_url('product_categories') );
					exit;

				} else {
					$error = "<strong>".t('error')."</strong>".t('bulkactionnotselecteditemyet');
				}

			}
			redirect( admin_url('product_categories') );
		}

		if($error){
			show_error($error, 503,t('actionfailed'));
			exit;
		}
	}

}
