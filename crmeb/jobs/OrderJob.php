<?php

namespace crmeb\jobs;

use crmeb\interfaces\JobInterface;
use think\facade\Db;
use think\queue\Job;

class OrderJob implements JobInterface
{

    public function fire(Job $job, $data): void
    {
        $doDefaultJod = $data['method'] ?? null;
        $beforeMethod = $data['beforeMethod'] ?? null;
        $jobData = $data['data'] ?? null;
        $errorTimes = $data['errorTimes'] ?? 0;
        $release = $data['release'] ?? 0;

        if (method_exists($this, $beforeMethod)) {
            $isJobStillNeedToBeDone = $this->{$beforeMethod}($jobData);
            if ($isJobStillNeedToBeDone) {
                $job->delete();
                return;
            }
        }

        if (method_exists($this, $doDefaultJod)) {
            $isJobDone = $this->{$doDefaultJod}($jobData);
            if ($isJobDone) {
                $job->delete();
                return;
            } else {
                if ($job->attempts() > $errorTimes && $errorTimes) {
                    $job->delete();
                    return;
                } else {
                    $job->release($release);
                }
            }
        }
    }

    public function doDefaultJod($data): bool
    {
        return Db::name('cache')->insert(['key'=>'test_'.rand(10,30),'result'=>json_encode($data)]);
    }
}
