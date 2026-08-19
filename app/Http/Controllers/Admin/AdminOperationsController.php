<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\OperationalIncident;
use App\Services\AdminAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminOperationsController extends Controller
{
    public function audits():View{return view('admin.audit-logs.index');}
    public function audit(AdminAuditLog $auditLog):View{return view('admin.audit-logs.show',compact('auditLog'));}
    public function incidents():View{return view('admin.incidents.index');}
    public function incident(OperationalIncident $incident):View{$incident->load('resolver');return view('admin.incidents.show',compact('incident'));}
    public function resolve(Request $request,OperationalIncident $incident,AdminAudit $audit):RedirectResponse{$data=$request->validate(['resolution_notes'=>['required','string','min:3','max:2000']]);$incident->update(['resolved_at'=>now(),'resolved_by'=>$request->user()->id,'resolution_notes'=>$data['resolution_notes']]);$audit->record($request,'incident.resolved',$incident);return back()->with('status','Incident resolved.');}
    public function reopen(Request $request,OperationalIncident $incident,AdminAudit $audit):RedirectResponse{$incident->update(['resolved_at'=>null,'resolved_by'=>null,'resolution_notes'=>null]);$audit->record($request,'incident.reopened',$incident);return back()->with('status','Incident reopened.');}
}
