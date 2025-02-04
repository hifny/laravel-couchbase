<?php declare(strict_types=1);

use Hifny\Couchbase\Query\Builder as Query;
use Hifny\Couchbase\Query\Grammar;

class QueryBuilderTest extends TestCase
{
    /**
     * @group QueryBuilderTest
     */
    public function tearDown()
    {
        DB::connection('couchbase-not-default')->table('users')->truncate();
        DB::connection('couchbase-not-default')->table('items')->truncate();
    }

    /**
     * @group QueryBuilderTest
     */
    public function testCollection()
    {
        $this->assertInstanceOf('Hifny\Couchbase\Query\Builder', DB::connection('couchbase-not-default')->table('users'));
    }

    /**
     * @group QueryBuilderTest
     */
    public function testGet()
    {
        $users = DB::connection('couchbase-not-default')->table('users')->get();
        $this->assertEquals(0, count($users));

        DB::connection('couchbase-not-default')->table('users')->useKeys('users::john_doe')->insert(['name' => 'John Doe']);

        $users = DB::connection('couchbase-not-default')->table('users')->get();
        $this->assertEquals(1, count($users));
    }

    /**
     * @group QueryBuilderTest
     */
    public function testNoDocument()
    {
        $items = DB::connection('couchbase-not-default')->table('items')->where('name', 'nothing')->get()->toArray();
        $this->assertEquals([], $items);

        $item = DB::connection('couchbase-not-default')->table('items')->where('name', 'nothing')->first();
        $this->assertEquals(null, $item);

        $item = DB::connection('couchbase-not-default')->table('items')->where('_id', '51c33d8981fec6813e00000a')->first();
        $this->assertEquals(null, $item);
    }

    /**
     * @group QueryBuilderTest
     */
    public function testInsert()
    {
        DB::connection('couchbase-not-default')->table('users')->useKeys('users::tags')->insert([
            'tags' => ['tag1', 'tag2'],
            'name' => 'John Doe',
        ]);

        $users = DB::connection('couchbase-not-default')->table('users')->get();
        $this->assertEquals(1, count($users));

        $user = $users[0];
        $this->assertEquals('John Doe', $user['name']);
        $this->assertTrue(is_array($user['tags']));
    }

    /**
     * @group QueryBuilderTest
     */
    public function testGetUnderscoreId()
    {
        $id = 'foobar.' . uniqid();
        DB::connection('couchbase-not-default')->table('users')->useKeys($id)->insert(['name' => 'John Doe']);
        $this->assertArrayHasKey('_id', DB::connection('couchbase-not-default')->table('users')->useKeys($id)->first());
        $this->assertSame($id, DB::connection('couchbase-not-default')->table('users')->useKeys($id)->first()['_id']);
    }

    /**
     * @group QueryBuilderTest
     */
    public function testInsertGetId()
    {
        $id = DB::connection('couchbase-not-default')->table('users')->insertGetId(['name' => 'John Doe']);
        $this->assertTrue(is_string($id));
        $this->assertSame($id, DB::connection('couchbase-not-default')->table('users')->useKeys($id)->first()['_id']);

        $id = DB::connection('couchbase-not-default')->table('users')->useKeys('foobar')->insertGetId(['name' => 'John Doe']);
        $this->assertSame('foobar', $id);
        $this->assertSame($id, DB::connection('couchbase-not-default')->table('users')->useKeys($id)->first()['_id']);
    }

    /**
     * @group QueryBuilderTest
     */
    public function testBatchInsert()
    {
        DB::connection('couchbase-not-default')->table('users')->useKeys('batch')->insert([
            [
                'tags' => ['tag1', 'tag2'],
                'name' => 'Jane Doe',
            ],
            [
                'tags' => ['tag3'],
                'name' => 'John Doe',
            ],
        ]);

        $users = DB::connection('couchbase-not-default')->table('users')->get();

        $this->assertEquals(2, count($users));
        $this->assertTrue(is_array($users[0]['tags']));
    }

    /**
     * @group QueryBuilderTest
     * @group testFind
     */
    public function testFind()
    {
        $id = 'my_id';
        DB::connection('couchbase-not-default')->table('users')->useKeys($id)->insert(['name' => 'John Doe']);

        $user = DB::connection('couchbase-not-default')->table('users')->find($id);
        $this->assertEquals('John Doe', $user['name']);
    }

    /**
     * @group QueryBuilderTest
     */
    public function testFindNull()
    {
        $user = DB::connection('couchbase-not-default')->table('users')->find(null);
        $this->assertEquals(null, $user);
    }

    /**
     * @group QueryBuilderTest
     */
    public function testCount()
    {
        DB::connection('couchbase-not-default')->table('users')->useKeys('users.1')->insert(['name' => 'Jane Doe']);
        DB::connection('couchbase-not-default')->table('users')->useKeys('users.2')->insert(['name' => 'Jane Doe']);

        $this->assertEquals(2, DB::connection('couchbase-not-default')->table('users')->count());
    }

    /**
     * @group QueryBuilderTest
     */
    public function testUpdate()
    {

        DB::connection('couchbase-not-default')->table('users')->useKeys('users.1')->insert(['name' => 'John Doe', 'age' => 30]);
        DB::connection('couchbase-not-default')->table('users')->useKeys('users.2')->insert(['name' => 'Jane Doe', 'age' => 20]);

        DB::connection('couchbase-not-default')->table('users')->where('name', 'John Doe')->update(['age' => 100]);
        $users = DB::connection('couchbase-not-default')->table('users')->get();

        $john = DB::connection('couchbase-not-default')->table('users')->where('name', 'John Doe')->first();
        $jane = DB::connection('couchbase-not-default')->table('users')->where('name', 'Jane Doe')->first();
        $this->assertEquals(100, $john['age']);
        $this->assertEquals(20, $jane['age']);
    }

    /**
     * @group QueryBuilderTest
     */
    public function testDelete()
    {

        DB::connection('couchbase-not-default')->table('users')->useKeys('users.1')->insert(['name' => 'John Doe', 'age' => 25]);
        DB::connection('couchbase-not-default')->table('users')->useKeys('users.2')->insert(['name' => 'Jane Doe', 'age' => 20]);

        DB::connection('couchbase-not-default')->table('users')->where('age', '<', 10)->delete();
        $this->assertEquals(2, DB::connection('couchbase-not-default')->table('users')->count());

        DB::connection('couchbase-not-default')->table('users')->where('age', '<', 25)->delete();
        $this->assertEquals(1, DB::connection('couchbase-not-default')->table('users')->count());
    }

    /**
     * @group QueryBuilderTest
     */
    public function testTruncate()
    {
        DB::connection('couchbase-not-default')->table('users')->useKeys('john')->insert(['name' => 'John Doe']);
        DB::connection('couchbase-not-default')->table('users')->truncate();
        $this->assertEquals(0, DB::connection('couchbase-not-default')->table('users')->count());
    }

    /**
     * @group QueryBuilderTest
     */
    public function testSubKey()
    {
        DB::connection('couchbase-not-default')->table('users')->useKeys('users.1')->insert([
            'name' => 'John Doe',
            'address' => ['country' => 'Belgium', 'city' => 'Ghent'],
        ]);
        DB::connection('couchbase-not-default')->table('users')->useKeys('users.2')->insert([
            'name' => 'Jane Doe',
            'address' => ['country' => 'France', 'city' => 'Paris'],
        ]);

        $users = DB::connection('couchbase-not-default')->table('users')->where('address.country', 'Belgium')->get();
        $this->assertEquals(1, count($users));
        $this->assertEquals('John Doe', $users[0]['name']);
    }

    /**
     * @group QueryBuilderTest
     */
    public function testInArray()
    {
        DB::connection('couchbase-not-default')->table('items')->useKeys('items.1')->insert([
            'tags' => ['tag1', 'tag2', 'tag3', 'tag4'],
        ]);
        DB::connection('couchbase-not-default')->table('items')->useKeys('items.2')->insert([
            'tags' => ['tag2'],
        ]);

        $items = DB::connection('couchbase-not-default')->table('items')->whereRaw('ARRAY_CONTAINS(tags, "tag2")')->get();
        $this->assertEquals(2, count($items));

        $items = DB::connection('couchbase-not-default')->table('items')->whereRaw('ARRAY_CONTAINS(tags, "tag1")')->get();
        $this->assertEquals(1, count($items));
    }

    /**
     * @group QueryBuilderTest
     */
    public function testDistinct()
    {
        DB::connection('couchbase-not-default')->table('items')->useKeys('item:1')->insert(['name' => 'knife', 'type' => 'sharp']);
        DB::connection('couchbase-not-default')->table('items')->useKeys('item:2')->insert(['name' => 'fork', 'type' => 'sharp']);
        DB::connection('couchbase-not-default')->table('items')->useKeys('item:3')->insert(['name' => 'spoon', 'type' => 'round']);
        DB::connection('couchbase-not-default')->table('items')->useKeys('item:4')->insert(['name' => 'spoon', 'type' => 'round']);

        $items = DB::connection('couchbase-not-default')->table('items')->select('name')->distinct()->get()->toArray();
        sort($items);
        $this->assertEquals(3, count($items));
        $this->assertEquals([['name' => 'fork'], ['name' => 'knife'], ['name' => 'spoon']], $items);

        $types = DB::connection('couchbase-not-default')->table('items')->select('type')->distinct()->get()->toArray();
        sort($types);
        $this->assertEquals(2, count($types));
        $this->assertEquals([['type' => 'round'], ['type' => 'sharp']], $types);
    }

    /**
     * @group QueryBuilderTest
     */
    public function testTake()
    {

        DB::connection('couchbase-not-default')->table('items')->useKeys('item.' . uniqid())->insert(['name' => 'knife', 'type' => 'sharp', 'amount' => 34]);
        DB::connection('couchbase-not-default')->table('items')->useKeys('item.' . uniqid())->insert(['name' => 'fork', 'type' => 'sharp', 'amount' => 20]);
        DB::connection('couchbase-not-default')->table('items')->useKeys('item.' . uniqid())->insert(['name' => 'spoon', 'type' => 'round', 'amount' => 3]);
        DB::connection('couchbase-not-default')->table('items')->useKeys('item.' . uniqid())->insert(['name' => 'spoon', 'type' => 'round', 'amount' => 14]);

        $items = DB::connection('couchbase-not-default')->table('items')->orderBy('name')->take(2)->get();
        $this->assertEquals(2, count($items));
        $this->assertEquals('fork', $items[0]['name']);
    }

    /**
     * @group QueryBuilderTest
     */
    public function testSkip()
    {
        DB::connection('couchbase-not-default')->table('items')->useKeys('item.' . uniqid())->insert(['name' => 'knife', 'type' => 'sharp', 'amount' => 34]);
        DB::connection('couchbase-not-default')->table('items')->useKeys('item.' . uniqid())->insert(['name' => 'fork', 'type' => 'sharp', 'amount' => 20]);
        DB::connection('couchbase-not-default')->table('items')->useKeys('item.' . uniqid())->insert(['name' => 'spoon', 'type' => 'round', 'amount' => 3]);
        DB::connection('couchbase-not-default')->table('items')->useKeys('item.' . uniqid())->insert(['name' => 'spoon', 'type' => 'round', 'amount' => 14]);

        $items = DB::connection('couchbase-not-default')->table('items')->orderBy('name')->skip(2)->get();
        $this->assertEquals(2, count($items));
        $this->assertEquals('spoon', $items[0]['name']);
    }

    /**
     * @group QueryBuilderTest
     */
    public function testPluck()
    {
        DB::connection('couchbase-not-default')->table('users')->useKeys('user.1')->insert(['name' => 'Jane Doe', 'age' => 20]);
        DB::connection('couchbase-not-default')->table('users')->useKeys('user.2')->insert(['name' => 'John Doe', 'age' => 25]);

        $age = DB::connection('couchbase-not-default')->table('users')->where('name', 'John Doe')->pluck('age')->toArray();
        $this->assertEquals([25], $age);
    }

    /**
     * @group QueryBuilderTest
     */
    public function testList()
    {

        DB::connection('couchbase-not-default')->table('items')->useKeys('item.' . uniqid())->insert(['name' => 'knife', 'type' => 'sharp', 'amount' => 34]);
        DB::connection('couchbase-not-default')->table('items')->useKeys('item.' . uniqid())->insert(['name' => 'fork', 'type' => 'sharp', 'amount' => 20]);
        DB::connection('couchbase-not-default')->table('items')->useKeys('item.' . uniqid())->insert(['name' => 'spoon', 'type' => 'round', 'amount' => 3]);
        DB::connection('couchbase-not-default')->table('items')->useKeys('item.' . uniqid())->insert(['name' => 'spoon', 'type' => 'round', 'amount' => 14]);

        $list = DB::connection('couchbase-not-default')->table('items')->pluck('name')->toArray();
        sort($list);
        $this->assertEquals(4, count($list));
        $this->assertEquals(['fork', 'knife', 'spoon', 'spoon'], $list);

        $list = DB::connection('couchbase-not-default')->table('items')->pluck('type', 'name')->toArray();
        $this->assertEquals(3, count($list));
        $this->assertEquals(['knife' => 'sharp', 'fork' => 'sharp', 'spoon' => 'round'], $list);

        $list = DB::connection('couchbase-not-default')->table('items')->pluck('name', '_id')->toArray();
        $this->assertEquals(4, count($list));
        $this->assertEquals(18, strlen(key($list)));
    }

    /**
     * @group QueryBuilderTest
     */
    public function testAggregate()
    {

        DB::connection('couchbase-not-default')->table('items')->useKeys('item.' . uniqid())->insert(['name' => 'knife', 'type' => 'sharp', 'amount' => 34]);
        DB::connection('couchbase-not-default')->table('items')->useKeys('item.' . uniqid())->insert(['name' => 'fork', 'type' => 'sharp', 'amount' => 20]);
        DB::connection('couchbase-not-default')->table('items')->useKeys('item.' . uniqid())->insert(['name' => 'spoon', 'type' => 'round', 'amount' => 3]);
        DB::connection('couchbase-not-default')->table('items')->useKeys('item.' . uniqid())->insert(['name' => 'spoon', 'type' => 'round', 'amount' => 14]);

        $this->assertEquals(71, DB::connection('couchbase-not-default')->table('items')->sum('amount'));
        $this->assertEquals(4, DB::connection('couchbase-not-default')->table('items')->count('amount'));
        $this->assertEquals(3, DB::connection('couchbase-not-default')->table('items')->min('amount'));
        $this->assertEquals(34, DB::connection('couchbase-not-default')->table('items')->max('amount'));
        $this->assertEquals(17.75, DB::connection('couchbase-not-default')->table('items')->avg('amount'));

        $this->assertEquals(2, DB::connection('couchbase-not-default')->table('items')->where('name', 'spoon')->count('amount'));
        $this->assertEquals(14, DB::connection('couchbase-not-default')->table('items')->where('name', 'spoon')->max('amount'));
    }

    /**
     * @group QueryBuilderTest
     */
    public function testSubdocumentAggregate()
    {
        DB::connection('couchbase-not-default')->table('items')->insert([
            ['name' => 'knife', 'amount' => ['hidden' => 10, 'found' => 3]],
            ['name' => 'fork', 'amount' => ['hidden' => 35, 'found' => 12]],
            ['name' => 'spoon', 'amount' => ['hidden' => 14, 'found' => 21]],
            ['name' => 'spoon', 'amount' => ['hidden' => 6, 'found' => 4]],
        ]);

        $this->assertEquals(65, DB::connection('couchbase-not-default')->table('items')->sum('amount.hidden'));
        $this->assertEquals(4, DB::connection('couchbase-not-default')->table('items')->count('amount.hidden'));
        $this->assertEquals(6, DB::connection('couchbase-not-default')->table('items')->min('amount.hidden'));
        $this->assertEquals(35, DB::connection('couchbase-not-default')->table('items')->max('amount.hidden'));
        $this->assertEquals(16.25, DB::connection('couchbase-not-default')->table('items')->avg('amount.hidden'));
    }

    /**
     * @group QueryBuilderTest
     */
    public function testUnset()
    {
        $id1 = DB::connection('couchbase-not-default')->table('users')->insertGetId(['name' => 'John Doe', 'note1' => 'ABC', 'note2' => 'DEF']);
        $id2 = DB::connection('couchbase-not-default')->table('users')->insertGetId(['name' => 'Jane Doe', 'note1' => 'ABC', 'note2' => 'DEF']);

        DB::connection('couchbase-not-default')->table('users')->where('name', 'John Doe')->unset('note1');

        $user1 = DB::connection('couchbase-not-default')->table('users')->find($id1);
        $user2 = DB::connection('couchbase-not-default')->table('users')->find($id2);

        $this->assertFalse(isset($user1['note1']));
        $this->assertTrue(isset($user1['note2']));
        $this->assertTrue(isset($user2['note1']));
        $this->assertTrue(isset($user2['note2']));

        DB::connection('couchbase-not-default')->table('users')->where('name', 'Jane Doe')->unset(['note1', 'note2']);

        $user2 = DB::connection('couchbase-not-default')->table('users')->find($id2);
        $this->assertFalse(isset($user2['note1']));
        $this->assertFalse(isset($user2['note2']));
    }

    /**
     * @group QueryBuilderTest
     * @group testUpdateSubdocument
     */
    public function testUpdateSubdocument()
    {
        $id = DB::connection('couchbase-not-default')->table('users')->insertGetId(['name' => 'John Doe', 'address' => ['country' => 'Belgium']]);

        DB::connection('couchbase-not-default')->table('users')->useKeys($id)->update(['address.country' => 'England']);

        $check = DB::connection('couchbase-not-default')->table('users')->find($id);
        $this->assertEquals('England', $check['address']['country']);
    }

    /**
     * @group QueryBuilderTest
     */
    public function testIncrement()
    {
        DB::connection('couchbase-not-default')->table('users')->insert([
            ['name' => 'John Doe', 'age' => 30, 'note' => 'adult'],
            ['name' => 'Jane Doe', 'age' => 10, 'note' => 'minor'],
            ['name' => 'Robert Roe', 'age' => null],
            ['name' => 'Mark Moe'],
        ]);

        $user = DB::connection('couchbase-not-default')->table('users')->where('name', 'John Doe')->first();
        $this->assertEquals(30, $user['age']);

        DB::connection('couchbase-not-default')->table('users')->where('name', 'John Doe')->increment('age');
        $user = DB::connection('couchbase-not-default')->table('users')->where('name', 'John Doe')->first();
        $this->assertEquals(31, $user['age']);

        DB::connection('couchbase-not-default')->table('users')->where('name', 'John Doe')->decrement('age');
        $user = DB::connection('couchbase-not-default')->table('users')->where('name', 'John Doe')->first();
        $this->assertEquals(30, $user['age']);

        DB::connection('couchbase-not-default')->table('users')->where('name', 'John Doe')->increment('age', 5);
        $user = DB::connection('couchbase-not-default')->table('users')->where('name', 'John Doe')->first();
        $this->assertEquals(35, $user['age']);

        DB::connection('couchbase-not-default')->table('users')->where('name', 'John Doe')->decrement('age', 5);
        $user = DB::connection('couchbase-not-default')->table('users')->where('name', 'John Doe')->first();
        $this->assertEquals(30, $user['age']);

        DB::connection('couchbase-not-default')->table('users')->where('name', 'Jane Doe')->increment('age', 10, ['note' => 'adult']);
        $user = DB::connection('couchbase-not-default')->table('users')->where('name', 'Jane Doe')->first();
        $this->assertEquals(20, $user['age']);
        $this->assertEquals('adult', $user['note']);

        DB::connection('couchbase-not-default')->table('users')->where('name', 'John Doe')->decrement('age', 20, ['note' => 'minor']);
        $user = DB::connection('couchbase-not-default')->table('users')->where('name', 'John Doe')->first();
        $this->assertEquals(10, $user['age']);
        $this->assertEquals('minor', $user['note']);

        DB::connection('couchbase-not-default')->table('users')->increment('age');
        $user = DB::connection('couchbase-not-default')->table('users')->where('name', 'John Doe')->first();
        $this->assertEquals(11, $user['age']);
        $user = DB::connection('couchbase-not-default')->table('users')->where('name', 'Jane Doe')->first();
        $this->assertEquals(21, $user['age']);
        $user = DB::connection('couchbase-not-default')->table('users')->where('name', 'Robert Roe')->first();
        $this->assertEquals(null, $user['age']);
    }

    /**
     * @group QueryBuilderTest
     */
    public function testWhere()
    {
        /** @var Query $query */
        $query = DB::connection('couchbase-not-default')->table('table1')->where('a', '=', 'b');
        $this->assertEquals('select `' . $query->from . '`.*, meta(`' . $query->from . '`).`id` as `_id` from `' . $query->from . '` where `eloquent_type` = "table1" and `a` = "b"',
            $this->queryToSql($query));
    }

    /**
     * @group QueryBuilderTest
     */
    public function testWhereWithTwoParameters()
    {
        /** @var Query $query */
        $query = DB::connection('couchbase-not-default')->table('table2')->where('a', 'b');
        $this->assertEquals('select `' . $query->from . '`.*, meta(`' . $query->from . '`).`id` as `_id` from `' . $query->from . '` where `eloquent_type` = "table2" and `a` = "b"',
            $this->queryToSql($query));
    }

    /**
     * @group QueryBuilderTest
     */
    public function testNestedWhere()
    {
        /** @var Query $query */
        $query = DB::connection('couchbase-not-default')->table('table3')->where(function (Query $query) {
            $query->where('a', 'b');
        });
        $this->assertEquals('select `' . $query->from . '`.*, meta(`' . $query->from . '`).`id` as `_id` from `' . $query->from . '` where `eloquent_type` = "table3" and (`a` = "b")',
            $this->queryToSql($query));
    }

    /**
     * @group QueryBuilderTest
     */
    public function testDictWhere()
    {
        /** @var Query $query */
        $query = DB::connection('couchbase-not-default')->table('table4')->where(['a' => 'b']);
        $this->assertEquals('select `' . $query->from . '`.*, meta(`' . $query->from . '`).`id` as `_id` from `' . $query->from . '` where `eloquent_type` = "table4" and (`a` = "b")',
            $this->queryToSql($query));
        $query = DB::connection('couchbase-not-default')->table('table5')->where(['a' => 'b', 'c' => 'd']);
        $this->assertEquals('select `' . $query->from . '`.*, meta(`' . $query->from . '`).`id` as `_id` from `' . $query->from . '` where `eloquent_type` = "table5" and (`a` = "b" and `c` = "d")',
            $this->queryToSql($query));
    }

    /**
     * @group QueryBuilderTest
     */
    public function testWhereColumnPreservedWord()
    {
        /** @var Query $query */
        $query = DB::connection('couchbase-not-default')->table('table6')->where('password', 'foobar');
        $this->assertEquals('select `' . $query->from . '`.*, meta(`' . $query->from . '`).`id` as `_id` from `' . $query->from . '` where `eloquent_type` = "table6" and `password` = "foobar"',
            $this->queryToSql($query));
    }

    /**
     * @group QueryBuilderTest
     */
    public function testWhereEscapedColumn()
    {
        /** @var Query $query */
        $query = DB::connection('couchbase-not-default')->table('table6')->where('`foo`', 'bar');
        $this->assertEquals('select `' . $query->from . '`.*, meta(`' . $query->from . '`).`id` as `_id` from `' . $query->from . '` where `eloquent_type` = "table6" and `foo` = "bar"',
            $this->queryToSql($query));
    }

    /**
     * @group QueryBuilderTest
     */
    public function testWhereEscapedColumnWithBacktick()
    {
        $query = DB::connection('couchbase-not-default')->table('table6')->where('`foo`bar`', 'bar');
        $this->assertEquals('select `' . $query->from . '`.*, meta(`' . $query->from . '`).`id` as `_id` from `' . $query->from . '` where `eloquent_type` = "table6" and `foo``bar` = "bar"',
            $this->queryToSql($query));
    }

    /**
     * @group QueryBuilderTest
     */
    public function testWhereColumnWithBacktickEnd()
    {
        $query = DB::connection('couchbase-not-default')->table('table6')->where('foo`', 'bar');
        $this->assertEquals('select `' . $query->from . '`.*, meta(`' . $query->from . '`).`id` as `_id` from `' . $query->from . '` where `eloquent_type` = "table6" and `foo``` = "bar"',
            $this->queryToSql($query));
    }

    /**
     * @group QueryBuilderTest
     */
    public function testWhereColumnWithBacktickInside()
    {
        $query = DB::connection('couchbase-not-default')->table('table6')->where('foo`bar', 'bar');
        $this->assertEquals('select `' . $query->from . '`.*, meta(`' . $query->from . '`).`id` as `_id` from `' . $query->from . '` where `eloquent_type` = "table6" and `foo``bar` = "bar"',
            $this->queryToSql($query));
    }

    /**
     * @group QueryBuilderTest
     */
    public function testWhereColumnWithBacktickBeginning()
    {
        $query = DB::connection('couchbase-not-default')->table('table6')->where('`foo', 'bar');
        $this->assertEquals('select `' . $query->from . '`.*, meta(`' . $query->from . '`).`id` as `_id` from `' . $query->from . '` where `eloquent_type` = "table6" and ```foo` = "bar"',
            $this->queryToSql($query));
    }

    /**
     * @group QueryBuilderTest
     */
    public function testWhereColumnWithBrackets()
    {
        $query = DB::connection('couchbase-not-default')->table('table6')->where('foo(abc)', 'foobar');
        $this->assertEquals('select `' . $query->from . '`.*, meta(`' . $query->from . '`).`id` as `_id` from `' . $query->from . '` where `eloquent_type` = "table6" and `foo(abc)` = "foobar"',
            $this->queryToSql($query));
    }

    /**
     * @group QueryBuilderTest
     */
    public function testWhereColumnUnderscoreId()
    {
        $query = DB::connection('couchbase-not-default')->table('table6')->where('_id', 'foobar');
        $this->assertEquals('select `' . $query->from . '`.*, meta(`' . $query->from . '`).`id` as `_id` from `' . $query->from . '` where `eloquent_type` = "table6" and meta(`' . $query->from . '`).`id` = "foobar"',
            $this->queryToSql($query));
    }

    /**
     * @group QueryBuilderTest
     */
    public function testWhereNestedRaw()
    {
        $query = DB::connection('couchbase-not-default')->table('table6')->where(function ($query) {
            $query->whereRaw('meta().id = "abc"')
                ->orWhere(DB::raw('substr(`a`, 0, 3)'), "def");
        });
        $this->assertEquals('select `' . $query->from . '`.*, meta(`' . $query->from . '`).`id` as `_id` from `' . $query->from . '` where `eloquent_type` = "table6" and (meta().id = "abc" or substr(`a`, 0, 3) = "def")',
            $this->queryToSql($query));
    }

    /**
     * @group QueryBuilderTest
     */
    public function testWhereDeepNestedRaw()
    {
        $query = DB::connection('couchbase-not-default')->table('table6')->where(function ($query) {
            $query->whereRaw('meta().id = "abc"')
                ->orWhere(function ($query) {
                    $query->whereRaw('substr(`b`, 0, 3) = "ghi"')
                        ->where(DB::raw('substr(`a`, 0, 3)'), "def");
                });
        });
        $this->assertEquals('select `' . $query->from . '`.*, meta(`' . $query->from . '`).`id` as `_id` from `' . $query->from . '` where `eloquent_type` = "table6" and (meta().id = "abc" or (substr(`b`, 0, 3) = "ghi" and substr(`a`, 0, 3) = "def"))',
            $this->queryToSql($query));
    }

    /**
     * @group QueryBuilderTest
     */
    public function testSelectColumnWithAs()
    {
        $query = DB::connection('couchbase-not-default')->table('table6')->select('foo as bar');
        $this->assertEquals('select `foo` as `bar` from `' . $query->from . '` where `eloquent_type` = "table6"',
            $this->queryToSql($query));
    }

    /**
     * @group QueryBuilderTest
     */
    public function testSelectColumnMetaId()
    {
        $query = DB::connection('couchbase-not-default')->table('table6')->select(DB::raw('meta().id as _id'));
        $this->assertEquals('select meta().id as _id from `' . $query->from . '` where `eloquent_type` = "table6"',
            $this->queryToSql($query));
    }

    /**
     * @group QueryBuilderTest
     */
    public function testSelectColumnUnderscoreId()
    {
        $query = DB::connection('couchbase-not-default')->table('table6')->select('_id');
        $this->assertEquals('select meta(`' . $query->from . '`).`id` as `_id` from `' . $query->from . '` where `eloquent_type` = "table6"',
            $this->queryToSql($query));
    }

    /**
     * @group QueryBuilderTest
     */
    public function testSelectColumnStar()
    {
        $query = DB::connection('couchbase-not-default')->table('table6')->select();
        $this->assertEquals('select `' . $query->from . '`.*, meta(`' . $query->from . '`).`id` as `_id` from `' . $query->from . '` where `eloquent_type` = "table6"',
            $this->queryToSql($query));

        $query = DB::connection('couchbase-not-default')->table('table6')->select('*');
        $this->assertEquals('select `' . $query->from . '`.*, meta(`' . $query->from . '`).`id` as `_id` from `' . $query->from . '` where `eloquent_type` = "table6"',
            $this->queryToSql($query));

        $query = DB::connection('couchbase-not-default')->table('table6')->select(['*']);
        $this->assertEquals('select `' . $query->from . '`.*, meta(`' . $query->from . '`).`id` as `_id` from `' . $query->from . '` where `eloquent_type` = "table6"',
            $this->queryToSql($query));
    }

    /**
     * @group QueryBuilderTest
     */
    public function testUseIndex()
    {
        $query = DB::connection('couchbase-not-default')->table('table6')->useIndex('test-index')->select();
        $this->assertEquals('select `'.$query->from.'`.*, meta(`'.$query->from.'`).`id` as `_id` from `' . $query->from . '` USE INDEX (`test-index` USING GSI) where `eloquent_type` = "table6"',
            $this->queryToSql($query));

        $query = DB::connection('couchbase-not-default')->table('table6')->useIndex('test-index', Grammar::INDEX_TYPE_VIEW)->select();
        $this->assertEquals('select `'.$query->from.'`.*, meta(`'.$query->from.'`).`id` as `_id` from `' . $query->from . '` USE INDEX (`test-index` USING VIEW) where `eloquent_type` = "table6"',
            $this->queryToSql($query));
    }

    /**
     * @group QueryBuilderTest
     */
    public function testWhereAnyIn()
    {
        $query = DB::connection('couchbase-not-default')->table('table6')->whereAnyIn('user_ids', ['123', '456']);
        $sql = $this->queryToSql($query);
        $this->assertEquals(1, preg_match('/ANY `([a-zA-Z0-9]+)`/', $sql, $match));
        $colIdentifier = $match[1];
        $this->assertEquals('select `' . $query->from . '`.*, meta(`' . $query->from . '`).`id` as `_id` from `' . $query->from . '` where `eloquent_type` = "table6" and ANY `' . $colIdentifier . '` IN `user_ids` SATISFIES `' . $colIdentifier . '` IN ["123", "456"] END',
            $sql);
    }

    /**
     * @param Query $query
     * @return string
     */
    private function queryToSql(Query $query)
    {
        return str_replace_array('?', array_map(function ($value) {
            return Grammar::wrapData($value);
        }, $query->getBindings()), $query->toSql());
    }
}
