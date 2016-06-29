<?php

/**
 *
 * User: 30feifei@gmail.com
 * Date: 2016/6/29
 * Time: 14:25
 */
class tcp
{
    private static $host = '127.0.0.1';
    private static $port = 9511;
    private $server = null;

    public function run()
    {
        $this->server = new swoole_server(self::$host, self::$port);
        $this->server->set(
            array(
                'worker_num'      => 2,
                'task_worker_num' => 2,
                'daemonize'       => 0,
                'max_request'     => 10000
            )
        );
        //添加触发事件及回调函数
        $this->server->on('start', 'tcp::onStart');
        $this->server->on('WorkerStart', 'tcp::onWorkerStart');
        $this->server->on('WorkerStop', function ($ser, $workerId) { });//worker进程终止时发生
        $this->server->on('connect', array('tcp', 'onConnect'));
        $this->server->on('Receive', 'tcp::onReceive');
        $this->server->on('Task', array('tcp', 'onTask'));
        $this->server->on('Finish', array('tcp', 'onFinish'));


        $this->server->start();
    }

    /**
     * 主进程开始
     * @param $ser
     */
    public static function onStart($ser)
    {
        //修改进程名
        swoole_set_process_name('mastar,tcp:' . self::$host . ':' . self::$port);
    }

    /**
     * worker进程/task进程启动时发生
     * @param \swoole_server $server
     * @param int            $worker_id
     */
    public static function onWorkerStart($ser, $worker_id)
    {
        //通过$worker_id参数的值来，判断worker是普通worker还是task_worker。$worker_id>= $serv->setting['worker_num'] 时表示这个进程是task_worker
        if ($worker_id >= $ser->setting['worker_num']) {
            swoole_set_process_name(self::$port . " task worker");
        } else {
            swoole_set_process_name(self::$port . " event worker");
        }
        //定时器
        if ((int)$worker_id === 1) {
            $ser->tick(50000, array('tcp', 'onTimer'));
        }
    }

    /**
     * 新的连接进入时触发 （发生在worker进程内），在UDP下没有onConnect/onClose事件
     * @param \swoole_server $ser
     * @param int            $fid
     * @param int            $from_id
     */
    public static function onConnect($ser, $fid, $from_id)
    {

    }

    /**
     * 接收到数据时回调此函数，发生在worker进程中
     * @param $ser
     * @param $fid
     * @param $form_id
     * @param $data
     */
    public static function onReceive($ser, $fid, $form_id, $data)
    {
        self::swooleLog(array('a' => __FUNCTION__, 'fid' => $fid, 'form' => $form_id, 'data' => $data));
        echo $data;
        $ser->task('worker task data');
        $ser->send($fid, 'swoole tcp ' . $data);
    }

    /**
     * 定时器触发
     * @param \swoole_server $ser
     * @param int            $interval
     */
    public static function onTimer($timer_id)
    {
        self::swooleLog(array('a' => __FUNCTION__, 'interval' => $timer_id, 'params' => func_get_args()));
    }

    /**
     * task_worker进程内被调用。worker进程可以使用swoole_server_task函数向task_worker进程投递新的任务
     * @param \swoole_server $ser
     * @param                $task_id
     * @param                $from_id
     * @param                $data
     */
    public static function onTask($ser, $task_id, $from_id, $data)
    {
        echo 'task_id', $task_id, 'from_id', $from_id, 'data', $data, PHP_EOL;
    }

    /**
     *
     * @param \swoole_server $serv
     * @param int            $task_id
     * @param string         $data
     */
    public static function onFinish($ser, $task_id, $data)
    {

    }

    /**
     * 重启所有worker进程
     */
    public function onReload()
    {
        $this->server->reload();
    }

    public static function callWrite()
    {

    }

    public static function swooleLog($arr)
    {
        $arr['time'] = microtime(true);
        swoole_async_write('/tmp/swoole_tcp.l', var_export($arr, true) . PHP_EOL, -1, 'tcp::callWrite');
    }

}

$tcp = new tcp();
$tcp->run();
