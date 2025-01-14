<?php

namespace Laravel\Telescope\Tests\Watchers;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Laravel\Telescope\EntryType;
use Illuminate\Support\Collection;
use Laravel\Telescope\Tests\FeatureTestCase;
use Laravel\Telescope\Watchers\QueryWatcher;

class QueryWatcherTest extends FeatureTestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app->get('config')->set('telescope.watchers', [
            QueryWatcher::class => [
                'enabled' => true,
                'slow' => 0.2,
            ],
        ]);
    }

    public function test_query_watcher_registers_database_queries()
    {
        $this->app->get('db')->table('telescope_entries')->count();

        $entry = $this->loadTelescopeEntries()->first();

        $this->assertSame(EntryType::QUERY, $entry->type);
        $this->assertSame('select count(*) as aggregate from "telescope_entries"', $entry->content['sql']);
        $this->assertSame('testbench', $entry->content['connection']);
        $this->assertFalse($entry->content['slow']);
    }

    public function test_query_watcher_can_tag_slow_queries()
    {
        $records = Collection::times(300, function () {
            return [
                'tag' => Str::random(),
            ];
        });

        $this->app->get('db')->table('telescope_monitoring')->insert($records->toArray());

        $entry = $this->loadTelescopeEntries()->first();

        $this->assertSame(EntryType::QUERY, $entry->type);
        $this->assertGreaterThan(300 * 16, strlen($entry->content['sql']));
        $this->assertSame('testbench', $entry->content['connection']);
        $this->assertTrue($entry->content['slow']);
    }

    public function test_query_watcher_can_prepare_bindings()
    {
        $this->app->get('db')->table('telescope_entries')
            ->where('type', 'query')
            ->where('should_display_on_index', true)
            ->whereNull('family_hash')
            ->where('created_at', '<', Carbon::parse('2019-01-01'))
            ->count();

        $entry = $this->loadTelescopeEntries()->first();

        $this->assertSame(EntryType::QUERY, $entry->type);
        $this->assertSame('select count(*) as aggregate from "telescope_entries" where "type" = \'query\' and "should_display_on_index" = 1 and "family_hash" is null and "created_at" < \'2019-01-01 00:00:00\'', $entry->content['sql']);
        $this->assertSame('testbench', $entry->content['connection']);
    }
}
