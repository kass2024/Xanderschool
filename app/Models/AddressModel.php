<?php

namespace App\Models;

use CodeIgniter\Model;

class AddressModel extends Model
{

	public function getProvince()
	{
		$data[] = array("id" => 1, "title" => "Kigali city");
		$data[] = array("id" => 2, "title" => "Northern");
		$data[] = array("id" => 3, "title" => "Southern");
		$data[] = array("id" => 4, "title" => "Eastern");
		$data[] = array("id" => 5, "title" => "Western");
		return $data;
	}
	public function getOneProvince($id)
	{
		foreach ($this->getProvince() as $p){
			if ($p["id"]==$id)
				return $p['title'];
		}
		return "";
	}
	public function getAddress($table, $val, $key,$single=false)
	{
		$builder = $this->setTable($table);
		$builder->where($key, $val)->orderBy("title","ASC");
		$data = $builder->get();
		if (!$single)
			return $data->getResultArray();
		return $data->getRowArray();
	}

}
