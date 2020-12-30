<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

use App\Models\Issue;
use App\Models\Section;
use App\Models\Article;

class IssueController extends Controller
{
    protected $settingsController;

    public function __construct(SettingsController $settings)
    {
        $this->settingsController = $settings;
    }

    public function show($id = null)
    {
        $singleIssue = $id != null;
        $issue = $singleIssue
                ? $this->getPublished($id)
                : $this->getLatestPublished();

        return Cache::rememberForever(self::buildCacheKey($id),
                                function() use ($issue, $singleIssue)
        {
            return view('layouts.issue', [
                'issue' => $issue,
                'sections' => Section::current()->get(),
                'aboutSection' => Section::forSlug('about')->first(),
                'topStories' => Article::inBucket('top_stories'),
                'look' => $this->settingsController->lookAndFeel(),
                'singleIssueView' => $singleIssue,
            ])->render();
        });
    }

    public static function buildCacheKey(?int $targetIssueId): string
    {
        $targetIssueId = $targetIssueId ?? 0;
        return "issue.$targetIssueId";
    }

    public static function clearCacheAllIssues()
    {
        /* Yes this is lazy, but whilst using a file cache driver we can't
         * use a tagged cache which would be a better way of doing this. */
        Cache::flush();
    }

    public static function clearCache(int $issueId)
    {
        Cache::forget(self::buildCacheKey($issueId));

        /* We have to clear the cached non-single-issue too in case changes
         * from the target issue made it into the non-specific issue. */
        Cache::forget(self::buildCacheKey(null));
    }

    public function getLatestPublished(): Issue
    {
        $query = Issue::select('*');
        return \App\applyPublishedCriteria($query)
                     ->orderByDesc('issue')->firstOrFail();
    }

    public function getPublished($id): Issue
    {
        $query = Issue::where('issue', $id);
        return \App\applyPublishedCriteria($query)->firstOrFail();
    }
}