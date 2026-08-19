<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\HoursEntry;
use App\Models\OperationalIncident;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDataController extends Controller
{
    public function users(Request $request): JsonResponse
    {
        $query=User::query()->withCount(['workspaces','hoursEntries']); if($request->boolean('trash'))$query=User::onlyTrashed()->withCount(['workspaces','hoursEntries']);
        return $this->respond($request,$query,['name','email','created_at'],function(User $user):array{return ['name'=>e($user->name).'<small>'.e($user->email).'</small>','status'=>$user->deleted_at?'Trashed':($user->suspended_at?'Suspended':($user->email_verified_at?'Verified':'Unverified')),'workspaces'=>$user->workspaces_count,'entries'=>$user->hours_entries_count,'joined'=>$user->created_at->format('d M Y'),'actions'=>view('admin.partials.user-actions',compact('user'))->render()];});
    }
    public function workspaces(Request $request): JsonResponse
    {
        $query=($request->boolean('trash')?Workspace::onlyTrashed():Workspace::query())->with('owner')->withCount(['users','hoursEntries']);
        return $this->respond($request,$query,['name','created_at'],function(Workspace $workspace):array{return ['name'=>e($workspace->name).'<small>'.e($workspace->default_break_minutes.'m '.$workspace->default_break_type).'</small>','owner'=>e($workspace->owner?->name??'Deleted user'),'members'=>$workspace->users_count,'entries'=>$workspace->hours_entries_count,'target'=>number_format($workspace->weekly_target_minutes/60,1).'h','actions'=>view('admin.partials.workspace-actions',compact('workspace'))->render()];});
    }
    public function hours(Request $request): JsonResponse
    {
        $query=($request->boolean('trash')?HoursEntry::onlyTrashed():HoursEntry::query())->with(['user','workspace']);
        return $this->respond($request,$query,['work_date','start_time','end_time','break_type','created_at'],function(HoursEntry $entry):array{return ['date'=>$entry->work_date->format('d M Y'),'user'=>e($entry->user?->name??'Deleted user'),'workspace'=>e($entry->workspace?->name??'Deleted workspace'),'time'=>substr($entry->start_time,0,5).'–'.substr($entry->end_time,0,5),'break'=>e($entry->break_minutes.'m '.$entry->break_type),'actions'=>view('admin.partials.hours-actions',compact('entry'))->render()];});
    }
    public function audits(Request $request): JsonResponse
    {
        $query=AdminAuditLog::query()->with('admin')->when($request->filled('action'),fn($q)=>$q->where('action',$request->input('action')))->when($request->filled('from'),fn($q)=>$q->whereDate('created_at','>=',$request->input('from')))->when($request->filled('to'),fn($q)=>$q->whereDate('created_at','<=',$request->input('to')));
        return $this->respond($request,$query,['action','created_at'],fn(AdminAuditLog $log)=>['action'=>e(str($log->action)->replace('.',' ')->headline()),'admin'=>e($log->admin?->name??'Deleted admin'),'target'=>e(($log->target_type ? class_basename($log->target_type) : 'Record').' #'.$log->target_id),'ip'=>e($log->ip_address??'—'),'date'=>$log->created_at->format('d M Y H:i'),'details'=>'<a href="'.route('admin.audit-logs.show',$log).'">View →</a>']);
    }
    public function incidents(Request $request): JsonResponse
    {
        $query=OperationalIncident::query()->when($request->input('status')==='open',fn($q)=>$q->whereNull('resolved_at'))->when($request->input('status')==='resolved',fn($q)=>$q->whereNotNull('resolved_at'))->when($request->filled('severity'),fn($q)=>$q->where('severity',$request->input('severity')));
        return $this->respond($request,$query,['reference','event_type','severity','submitted_email','occurred_at'],fn(OperationalIncident $incident)=>['reference'=>e($incident->reference),'event'=>e(str($incident->event_type)->replace('.',' ')->headline()),'severity'=>e(ucfirst($incident->severity)),'email'=>e($incident->submitted_email??'—'),'status'=>$incident->resolved_at?'Resolved':'Open','date'=>$incident->occurred_at->format('d M Y H:i'),'details'=>'<a href="'.route('admin.incidents.show',$incident).'">View →</a>']);
    }
    private function respond(Request $request,Builder $query,array $columns,callable $map):JsonResponse
    {
        $draw=max(0,$request->integer('draw'));$start=max(0,$request->integer('start'));$length=min(100,max(10,$request->integer('length',20)));$total=(clone $query)->count();$search=trim((string)data_get($request->input('search',[]),'value',''));
        if($search!=='')$query->where(function($q)use($columns,$search){foreach($columns as $i=>$column){$i===0?$q->where($column,'like',"%{$search}%"):$q->orWhere($column,'like',"%{$search}%");}});
        $filtered=(clone $query)->count();$orderIndex=(int)data_get($request->input('order',[]),'0.column',0);$direction=data_get($request->input('order',[]),'0.dir')==='asc'?'asc':'desc';$query->orderBy($columns[$orderIndex]??end($columns),$direction);
        return response()->json(['draw'=>$draw,'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'data'=>$query->skip($start)->take($length)->get()->map($map)->values()]);
    }
}
