<?php declare(strict_types=1);

use Hifny\Couchbase\Eloquent\Model as Eloquent;

class Book extends Eloquent
{
    protected $connection = 'couchbase-not-default';
    protected $table = 'books';
    protected static $unguarded = true;
    protected $primaryKey = 'title';

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function mysqlAuthor()
    {
        return $this->belongsTo('MysqlUser', 'author_id');
    }
}
