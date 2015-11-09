<?php
/**
 * memcache-manage tool
 */
class MemcachedManage
{
	const DEFAULT_LIMIT = 10;

	public function __construct($argv)
	{
		$this->target_host		= '';
		$this->target_port		= '';
		$this->target_operation	= '';
		$this->option_args_1	= '';
		$this->option_args_2	= '';

		//operations
		$this->operation_config	= array(
			'stats'			=> 'show memcached-stats',
			'stats_slabs'	=> 'shows slabs info',
			'dump'			=> 'dumps keys and values, (option) $target_slab $dump_num(default is 10)',
			'get_keys'		=> 'get keys, (option) $target_slab $dump_num(default is 10)',
			'get_item'		=> 'get item from key, (option) $key',
		);


		//set target_host
		if(isset($argv[1])){
			$this->target_host	= $argv[1];
		}
		//set target_port
		if(isset($argv[2])){
			$this->target_port	= $argv[2];
		}
		//set target_operation
		if(isset($argv[3]) && isset($this->operation_config[$argv[3]])){
			$this->target_operation	= $argv[3];
		}
		if(isset($argv[4])){
			$this->option_args_1	= $argv[4];
		}
		if(isset($argv[5])){
			$this->option_args_2	= $argv[5];
		}

		//check_args
		$error_args = 0;
		if(!$this->target_host){
			echo "FALSE host \n";
			$error_args = 1;
		}
		if(!is_numeric($this->target_port)){
			echo "FALSE port \n";
			$error_args = 1;
		}
		if(!$this->target_operation){
			echo "FALSE operation \n";
			$error_args = 1;
		}

		if($error_args){
			$this->show_usage();
		}
	}

	//
	public function run()
	{
		switch ($this->target_operation) {
			case 'stats':
				$this->stats();
				break;
			case 'stats_slabs':
				$this->stats_slabs();
				break;
			case 'dump':
				$this->dump();
				break;
			case 'get_keys':
				$this->get_keys();
				break;
			case 'get_item':
				$this->get_item();
				break;
			default:
				# code...
				break;
		}
	}

	//show memcached stats
	private function stats()
	{
		$cmd = "echo 'stats' | nc {$this->target_host} {$this->target_port}";
		//unix domain sock
		if(strpos($this->target_host, 'sock') && $this->target_port == 0){
			$cmd = "echo 'stats' | nc -U {$this->target_host}";
		}

		exec($cmd, $output, $return_var);

		if($return_var == 0){
			foreach ($output as $row) {
				echo $row. "\n";
			}
		}else{
			echo 'fail connect';
			exit;
		}

		return;
	}

	//show slabs stats
	private function stats_slabs()
	{
		$slab_arr		= array();

		$cmd = "echo 'stats items' | nc {$this->target_host} {$this->target_port}";
		//unix domain sock
		if(strpos($this->target_host, 'sock') && $this->target_port == 0){
			$cmd = "echo 'stats items' | nc -U {$this->target_host}";
		}
		exec($cmd, $output, $return_var);

		if($return_var == 0){
			foreach ($output as $row) {
				$tmp_arr	= explode(' ', $row);
				$tmp_info	= isset($tmp_arr[1])	? explode(':', $tmp_arr[1]) : array();

				$slab_num	= isset($tmp_info[1])	? $tmp_info[1]	: '';
				$item		= isset($tmp_info[2])	? $tmp_info[2]	: '';
				$num		= isset($tmp_arr[2])	? $tmp_arr[2]	: '';
				if(is_numeric($slab_num)){
					if(!isset($slab_arr[$slab_num])) $slab_arr[$slab_num] = array();
					$slab_arr[$slab_num][$item] = $num;
				}
			}
		}else{
			echo 'fail connect';
			exit;
		}

		foreach ($slab_arr as $slab_num => $slab_info) {
			echo "\nslab_num : {$slab_num}\n";
			foreach ($slab_info as $key => $val) {
				echo "\t{$key} => {$val}\n";
			}
		}

		return;
	}

	//dump key => item
	private function dump()
	{
		$slab	= $this->option_args_1 ? $this->option_args_1 : 0;
		$limit	= $this->option_args_2 ? $this->option_args_2 : self::DEFAULT_LIMIT;
		$key_list = $this->get_key_list($slab, $limit);

		if($key_list){
			$this->init_memcached();
			foreach ($key_list as $row) {
				$item = $this->memcached->get($row);
				echo $row . " => \n";
				var_dump($item);
				echo "\n";
			}
		}

		return;
	}

	//get cached_key
	private function get_keys()
	{
		$slab	= $this->option_args_1 ? $this->option_args_1 : 0;
		$limit	= $this->option_args_2 ? $this->option_args_2 : self::DEFAULT_LIMIT;

		$key_list = $this->get_key_list($slab, $limit);
		if($key_list){
			foreach ($key_list as $key => $val) {
				echo "{$val}\n";
			}
		}

		return;
	}

	//get cached_item
	private function get_item()
	{
		$key	= $this->option_args_1 ? $this->option_args_1 : '';
		if($key){
			$this->init_memcached();
			$item = $this->memcached->get($key);
			echo $key . " => \n";
			var_dump($item);
			echo "\n";
		}else{
			echo 'no input key';
			exit;
		}

		return;
	}

	//get key list
	private function get_key_list($slab=0, $limit=0)
	{
		$key_list = array();
		//no select slab
		if(!$slab){
			$this->init_memcached();
			$key_list = $this->memcached->getAllKeys();
			if($limit && $key_list) $key_list = array_slice($key_list, 0, $limit);
		//select slab
		}else{
			$cmd = "echo 'stats cachedump {$slab} {$limit}' | nc {$this->target_host} {$this->target_port}";
			exec($cmd, $output, $return_var);
			if($return_var == 0){
				foreach ($output as $row) {
					$tmp_arr	= explode(' ', $row);
					if(isset($tmp_arr[1]) && $tmp_arr[1]){
						$key_list[] = $tmp_arr[1];
					}
					if(count($key_list) >= $limit) break;
				}
			}
		}

		return $key_list;
	}

	//init memcached
	private function init_memcached()
	{
		if(isset($this->memcached)) return;
		$this->memcached = new Memcached();
		if (!$this->memcached->addServer($this->target_host, $this->target_port)) {
			echo 'fail memcached->addServer';
			exit;
		}

		return;
	}

	//show usage
	private function show_usage()
	{
		echo "\n";
		echo 'USAGE' . "\n";
		echo 'ex : php memcached_manage.php host port operation' . "\n";
		echo 'host : [xxx.xxx.xx.xx]' . "\n";
		echo 'port : [11211|/tmp/memcache.sock]' . "\n";
		echo 'operation 	: ' . "\n";
		foreach ($this->operation_config as $key => $val) {
			echo "	{$key}	: {$val} \n";
		}
		exit;
	}

}

if (isset($argv)) {
	$obj = new MemcachedManage($argv);
	$obj->run();
}
