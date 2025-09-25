<?php
class webapp_extend_nfs_echo extends webapp_echo_html
{
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp, 'nfs', 'get_home');
		$this->stylesheet('/webapp/extend/nfs/echo.css');
		$this->title('NFS');
		if ($webapp instanceof webapp_extend_nfs)
		{
			$this->nav([
				['Explorer', "?{$this->routename}"]
			]);
		}
		else
		{
			$webapp->break($this->ignore(...));
		}
	}

	function ignore()
	{
		$this->title('Ignore');
		$this->main->append('h2', 'WebApp Must extend NFS');
		return 500;
	}
	function authenticate(...$params):array
	{
		return $this->webapp->admin(...$params);
	}
	function get_asd()
	{

	}


	function get_home(int $sort = 0, int $page = 1, string $search = NULL, string $node = NULL)
	{

		$cond = $this->webapp->cond();
		



		

		$table = $this->main->table($cond($this->webapp->mysql->{$this->webapp::tablename})->paging($page, 10), function($table, $value, $type)
		{
			$table->row();
			$table->cell($value['hash']);

			$table->cell($value['sort']);
			$table->cell($type[$value['type']] ?? "mixed({$value['type']})");
			$table->cell(date('Y-m-d\TH:i:s', $value['t0']));
			$table->cell(date('Y-m-d\TH:i:s', $value['t1']));
			$table->cell($value['size']);
			$table->cell($value['views']);
			$table->cell($value['likes']);
			$table->cell($value['shares']);

			$table->cell()->details_button_popup($value['name'], [
				['Access Object URL', strstr($this->webapp->src($value), '#', TRUE), 'target' => '_blank'],
				['Rename', "#"],
				['Delete', "#", 'class' => 'danger']
			]);

		}, ['tree', 'file']);
		$table->fieldset('Hash', 'Sort', 'Type', 'Insert Time', 'Update Time', 'Size', 'Views', 'Likes', 'Shares', 'Name');
		$table->header('Explorer');
		$table->paging($this->webapp->at(['page' => '']));
		//$table->bar->append('input', ['type' => 'number', 'min' => 0, 'mix' => 255, 'value' => $sort]);
		$table->bar->select(range(0, 255));
		$table->bar->append('input', ['type' => 'search', 'placeholder' => 'Type keyword search', 'value' => $search]);
		$table->bar->append('a', ['Create folder', 'href' => "?{$this->routename}/create-folder,sort:{$sort}"]);
		$table->bar->append('a', ['Upload file', 'href' => "?{$this->routename}/uploadfile,sort:{$sort}", 'class' => 'default']);
	}
	function form_create_folder(webapp_html $node = NULL)
	{
		$form = new webapp_form($node ?? $this->webapp);



		$form->field('name', 'text', ['placeholder' => 'Folder name', 'required' => NULL]);


		$form->button('Create Folder', 'submit');
		$form->fieldset('Extdata');
		$form->field('extdata', 'textarea', ['placeholder' => 'JSON String', 'rows' => 8]);
		return $form;
	}
	function get_create_folder(int $sort)
	{
		$this->form_create_folder($this->main);
	}
	function post_create_folder(int $sort)
	{
		$this->form_create_folder()->fetch($data);
		if (empty($data['extdata']))
		{
			unset($data['extdata']);
		}
		
		// json_validate($data['extdata'])

		// var_dump( );
		

		var_dump( $this->webapp->nfs($sort, 0)->create($data) );
		print_r($data);
	}


	function form_uploadfile(webapp_html $node = NULL)
	{
		$form = new webapp_form($node ?? $this->webapp);
		$form->progress();//->setattr(['value' => 0.47]);


		$form->fieldset('File / Submit');
		$form->field('file', 'file', ['required' => NULL]);
		

		$form->button('Uploadfile', 'submit');
		$form->fieldset('Extdata');
		$form->field('extdata', 'textarea', ['placeholder' => 'JSON String', 'rows' => 8]);
		return $form;
	}
	function get_uploadfile()
	{
		$this->form_uploadfile($this->main);
	}



}