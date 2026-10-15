<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ImportRun extends Model {
    protected $fillable = ['source','status','started_at','finished_at','projects_fetched','projects_created','projects_updated','projects_skipped','errors'];
    protected function casts(): array { return ['started_at'=>'datetime','finished_at'=>'datetime','errors'=>'array']; }
}
