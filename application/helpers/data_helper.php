<?php if (!defined('BASEPATH')) exit('No direct script access allowed');


// -----------------------------------------------------------------------------
function getUserbyId($id)
{
	$CI = &get_instance();
	return $CI->db->get_where('profil', array('id_user' => $id))->row_array()['nama'];
}


function indonesian_date ($timestamp = '', $date_format = 'j F Y', $suffix = 'WIB') {
	if (trim ($timestamp) == '')
	{
			$timestamp = time ();
	}
	elseif (!ctype_digit ($timestamp))
	{
		$timestamp = strtotime ($timestamp);
	}
	# remove S (st,nd,rd,th) there are no such things in indonesia :p
	$date_format = preg_replace ("/S/", "", $date_format);
	$pattern = array (
		'/Mon[^day]/','/Tue[^sday]/','/Wed[^nesday]/','/Thu[^rsday]/',
		'/Fri[^day]/','/Sat[^urday]/','/Sun[^day]/','/Monday/','/Tuesday/',
		'/Wednesday/','/Thursday/','/Friday/','/Saturday/','/Sunday/',
		'/Jan[^uary]/','/Feb[^ruary]/','/Mar[^ch]/','/Apr[^il]/','/May/',
		'/Jun[^e]/','/Jul[^y]/','/Aug[^ust]/','/Sep[^tember]/','/Oct[^ober]/',
		'/Nov[^ember]/','/Dec[^ember]/','/January/','/February/','/March/',
		'/April/','/June/','/July/','/August/','/September/','/October/',
		'/November/','/December/',
	);
	$replace = array ( 'Sen','Sel','Rab','Kam','Jum','Sab','Min',
		'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu',
		'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des',
		'Januari','Februari','Maret','April','Juni','Juli','Agustus','Sepember',
		'Oktober','November','Desember',
	);
	$date = date ($date_format, $timestamp);
	$date = preg_replace ($pattern, $replace, $date);
	$date = "{$date} {$suffix}";
	return $date;
} 

function getUserPhoto($id)
{

    $CI = &get_instance();
    return $CI->db->get_where('profil', array('id_user' => $id))->row_array()['photo'];
}

function transposeData($data)
{
    $retData = array();
    foreach ($data as $row => $columns) {
        foreach ($columns as $row2 => $column2) {
            $retData[$row2][$row] = $column2;
        }
    }
    return $retData;
}

function countSurat($status) {
	$CI = &get_instance();
	$query = $CI->db->query("SELECT COUNT(*) as JUMLAH
		FROM surat_status s
		WHERE s.id_surat NOT IN (SELECT ss.id_surat FROM surat_status ss WHERE ss.id_status='2')
        ");
		$result = $query->row_array();
		return $result['JUMLAH'];
}





