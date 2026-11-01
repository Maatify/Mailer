<?php

namespace Maatify\Mailer\Tests;

use PHPUnit\Framework\TestCase;

/**
 * scripts/ci/check-whitespace.sh must judge only what a branch changed. The first
 * push of a new branch (an all-zero CI_DIFF_BASE) is compared with the merge base
 * with origin/master, so whitespace defects inherited from master do not fail it;
 * without a common history the whole tree is checked.
 */
final class WhitespaceScriptTest extends TestCase
{
    private string $repo = '';

    protected function setUp(): void
    {
        $this->repo = sys_get_temp_dir() . '/whitespace-test-' . bin2hex(random_bytes(4));
        mkdir($this->repo);
    }

    protected function tearDown(): void
    {
        shell_exec('rm -rf ' . escapeshellarg($this->repo));
    }

    public function testInheritedDefectsDoNotFailTheFirstPushOfANewBranch(): void
    {
        $this->git('init -q -b master');
        file_put_contents($this->repo . '/old.txt', "inherited defect   \n");
        $this->commit('master');
        $this->git('update-ref refs/remotes/origin/master HEAD');
        $this->git('checkout -q -b release/x');
        file_put_contents($this->repo . '/new.txt', "clean\n");
        $this->commit('branch');

        $this->assertSame(0, $this->check(str_repeat('0', 40)));
    }

    public function testADefectIntroducedByTheBranchFailsTheFirstPush(): void
    {
        $this->git('init -q -b master');
        file_put_contents($this->repo . '/old.txt', "clean\n");
        $this->commit('master');
        $this->git('update-ref refs/remotes/origin/master HEAD');
        $this->git('checkout -q -b release/x');
        file_put_contents($this->repo . '/new.txt', "introduced defect   \n");
        $this->commit('branch');

        $this->assertNotSame(0, $this->check(str_repeat('0', 40)));
    }

    public function testWithoutACommonHistoryTheWholeTreeIsChecked(): void
    {
        $this->git('init -q -b master');
        file_put_contents($this->repo . '/old.txt', "clean\n");
        $this->commit('master');
        $this->git('update-ref refs/remotes/origin/master HEAD');
        $this->git('checkout -q --orphan unrelated');
        file_put_contents($this->repo . '/defect.txt', "defect in an unrelated history   \n");
        $this->git('add -A');
        $this->commit('unrelated');

        $this->assertNotSame(0, $this->check(str_repeat('0', 40)));
    }

    private function git(string $arguments): void
    {
        shell_exec(sprintf('cd %s && git %s 2>&1', escapeshellarg($this->repo), $arguments));
    }

    private function commit(string $message): void
    {
        $this->git('add -A');
        shell_exec(sprintf(
            'cd %s && GIT_AUTHOR_NAME=t GIT_AUTHOR_EMAIL=t@example.test GIT_COMMITTER_NAME=t GIT_COMMITTER_EMAIL=t@example.test git commit -q -m %s 2>&1',
            escapeshellarg($this->repo),
            escapeshellarg($message)
        ));
    }

    private function check(string $base): int
    {
        exec(sprintf(
            'cd %s && CI_DIFF_BASE=%s bash %s >/dev/null 2>&1',
            escapeshellarg($this->repo),
            escapeshellarg($base),
            escapeshellarg(__DIR__ . '/../scripts/ci/check-whitespace.sh')
        ), $output, $code);

        return $code;
    }
}
