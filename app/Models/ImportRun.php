<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ImportRun extends Model {
    protected $fillable = ['source','collector_type','status','started_at','finished_at','projects_fetched','projects_created','projects_updated','projects_skipped','projects_rejected','projects_failed','notifications_inspected','unique_projects_discovered','details_succeeded','details_failed','errors'];
    protected function casts(): array { return ['started_at'=>'datetime','finished_at'=>'datetime','errors'=>'array']; }
}
