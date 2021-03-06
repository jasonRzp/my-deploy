<?php

namespace App\Services;

use Symfony\Component\Process\Process;
use Log;
use App\Jobs\HandGitRepoJob;
set_time_limit(0);

class GitRepoInfoService
{
    public static function handleQueue($arr, $id)
    {
        Log::info('the arr: '.print_r($arr, true)." and the id: ".$id);
        $job = (new HandGitRepoJob($arr, $id))->onQueue('handle_git_repo');
        dispatch($job);
    }
    /**
     * @param $gitName
     * @return bool
     */
    public static function getGitInfo($sshAddr, $gitUser, $branch, $configId)
    {
        // clone或者pull对应的仓库
        if (!isset($gitUser) || empty($gitUser) || !is_string($gitUser)
            || !isset($configId) || empty($configId)
            || !isset($sshAddr) || empty($sshAddr)) {
            return false;
        }
        Log::info('the ssh addr: '.$sshAddr.'git user: '.$gitUser.' and the branch: '.$branch.' and the config Id: '.$configId);

        $repoPrefix = config('deployment.git_prefix');
//        $fullGitPath = $repoPrefix . $gitName . '.git';
//        $fullGitPath = 'http://zhenpeng8:20181203%40rzp@git.intra.weibo.com/user_growth/msg-new.git';
        $srcPath = config('deployment.src_path');
        $sshArr = explode('/', $sshAddr);
        $gitName = $sshArr[count($sshArr) - 1];
        $pos = strrpos($gitName,'.');
        $gitName = substr($gitName, 0, $pos);
        Log::info('the git name: '.$gitName);
        $srcGitPath = $srcPath.'/'.$gitName;

        if (is_numeric($branch)) {
            $configArr = config('deployment.deploy_config');
            $branchArr = $configArr['task_branch'];
            $branch = $branchArr[$branch];
        }

        try {
            if (!file_exists($srcGitPath)) {
                $action = 'clone';
//                $command = "nohup sudo -iu {$gitUser} sh -c 'cd {$srcPath} && git clone {$fullGitPath}' && sh switchBranch.sh {$gitName} {$action} {$branch} {$configId}";
                if ($gitUser == 'root') {
                    $command = "nohup cd {$srcPath} ; sh switchBranch.sh \"{$sshAddr}\" \"{$gitName}\" \"{$action}\" \"{$branch}\" \"{$configId}\"";
                } else {
                    $command = "nohup sudo -iu {$gitUser} sh -c 'cd {$srcPath} ; sh switchBranch.sh \"{$sshAddr}\" \"{$gitName}\" \"{$action}\" \"{$branch}\" \"{$configId}\"'";
                }
//                $ret = exec("$command > /dev/null 2>&1 &", $output);
                //Log::info('Clone: the cmd is '.$command . ' and the ret is '.json_encode($output) . ' & the ret: '.print_r($ret, true). ' the output: '.print_r($output, true));
            } else {
                $action = 'pull';
//                $command = "nohup sudo -iu {$gitUser} sh -c 'cd {$srcPath} && cd {$gitName} && sh switchBranch.sh {$gitName} {$action} {$branch} {$configId} && git pull $fullGitPath";
                $command = "nohup sudo -iu {$gitUser} sh -c 'cd {$srcPath} ; sh switchBranch.sh \"{$sshAddr}\" \"{$gitName}\" \"{$action}\" \"{$branch}\" \"{$configId}\"'";
//                $ret = exec("$command > /dev/null 2>&1 &", $output);
            }
            Log::info('Action: the cmd is '.$command);

            try {
                $process = new Process($command);
                $process->setTimeout(360);
                $process->start();
                $iterator = $process->getIterator($process::ITER_SKIP_ERR | $process::ITER_KEEP_OUTPUT);
                $string = '';
                foreach ($iterator as $data) {
                    $string .= $data;
                }

                Log::info('message: '.print_r($string, true));
                // 将输出结果，记录到log中
//                    $this->getOutputTo($string);

                if (!$process->isSuccessful()) { // deploy 失败，操作失败的提示信息
                    Log::info('error: '.json_encode($process->getErrorOutput()));
                    Log::info(json_encode($command).'执行失败。');
                    return false;
                }
            } catch (\Exception $e) {
                Log::info('mage op failed, catch the exception info: '.$e->getMessage());
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::info('exception info is '.$e->getMessage());
            return 'fail';
        }
    }
}