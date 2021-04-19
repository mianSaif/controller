<?php

namespace App\Http\Controllers;

use App\Department;
use App\Designation;
use App\Employee;
use App\Plan;
use App\SalaryType;
use App\User;
use App\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class EmployeeController extends Controller
{

    public function index(Request $request)
    {
        if(\Auth::user()->type == 'company' || \Auth::user()->type == 'employee')
        {
            $status = Employee::$statues;

            $department = Department::where('created_by', '=', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $department->prepend('All');
            $designation = Designation::where('created_by', '=', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $designation->prepend('All', '');

            $employees = User::select('users.*', 'employees.department', 'employees.designation')->leftJoin('employees', 'users.id', '=', 'employees.user_id')->where('type', 'employee')->where('users.created_by', '=', \Auth::user()->creatorId());

            if(!empty($request->department))
            {
                $employees->where('employees.department', $request->department);
            }
            if(!empty($request->designation))
            {
                $employees->where('employees.designation', $request->designation);
            }
            $employees = $employees->get();

            return view('employee.index', compact('status', 'department', 'designation', 'employees'));

        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

    }


    public function create()
    {
        return view('employee.create');

    }


    public function store(Request $request)
    {

        if(\Auth::user()->type == 'company')
        {
            $validator = \Validator::make(
                $request->all(), [
                                   'name' => 'required|max:120',
                                   'email' => 'required|email|unique:users',
                                   'password' => 'required|min:6',
                               ]
            );
            if($validator->fails())
            {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            $authUser      = \Auth::user();
            $creator       = User::find($authUser->creatorId());
            $totalEmployee = $authUser->countEmployees($creator->id);
            $plan          = Plan::find($creator->plan);
            if($totalEmployee < $plan->max_employee || $plan->max_employee == -1)
            {
                $user             = new User();
                $user->name       = $request->name;
                $user->email      = $request->email;
                $user->password   = Hash::make($request->password);
                $user->type       = 'employee';
                $user->lang       = 'en';
                $user->created_by = \Auth::user()->creatorId();
                $user->avatar     = '';
                $user->save();

                if(!empty($user))
                {
                    $employee              = new Employee();
                    $employee->user_id     = $user->id;
                    $employee->employee_id = $this->employeeNumber();
                    $employee->created_by  = \Auth::user()->creatorId();
                    $employee->save();
                }

                $uArr = [
                    'email' => $user->email,
                    'password' => $request->password,
                ];

                $resp = Utility::sendEmailTemplate('create_user', [$user->id => $user->email], $uArr);


                return redirect()->route('employee.index')->with('success', __('Employee created Successfully.') . ((!empty($resp) && $resp['is_success'] == false && !empty($resp['error'])) ? '<br> <span class="text-danger">' . $resp['error'] . '</span>' : ''));

            }
            else
            {
                return redirect()->back()->with('error', __('Your employee limit is over, Please upgrade plan.'));

            }

        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }


    }


    public function show($id)
    {
        if(\Auth::user()->type == 'company' || \Auth::user()->type == 'employee')
        {
            $eId  = \Crypt::decrypt($id);
            $user = User::find($eId);

            $employee = Employee::where('user_id', $eId)->first();

            return view('employee.view', compact('user', 'employee'));
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

    }


    public function edit($id)
    {
        $eId        = \Crypt::decrypt($id);
        $department = Department::where('created_by', '=', \Auth::user()->creatorId())->get()->pluck('name', 'id');
        $department->prepend('Select Department', '');
        $designation = Designation::where('created_by', '=', \Auth::user()->creatorId())->get()->pluck('name', 'id');
        $designation->prepend('Select Designation', '');
        $salaryType = SalaryType::where('created_by', '=', \Auth::user()->creatorId())->get()->pluck('name', 'id');
        $salaryType->prepend('Select Type', '');

        $user = User::find($eId);

        $employee = Employee::where('user_id', $eId)->first();

        return view('employee.edit', compact('user', 'employee', 'department', 'designation', 'salaryType'));
    }


    public function update(Request $request, $id)
    {
        if(\Auth::user()->type == 'company')
        {
            $validator = \Validator::make(
                $request->all(), [
                                   'dob' => 'required',
                                   'gender' => 'required',
                                   'address' => 'required',
                                   'mobile' => 'required',
                                   'department' => 'required',
                                   'designation' => 'required',
                                   'joining_date' => 'required',
                                   'salary_type' => 'required',
                                   'salary' => 'required',
                               ]
            );

            if($validator->fails())
            {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            if(!empty($request->name))
            {
                $user       = User::find($id);
                $user->name = $request->name;
                $user->save();
            }

            $employee               = Employee::where('user_id', $id)->first();
            $employee->gender       = $request->gender;
            $employee->address      = $request->address;
            $employee->mobile       = $request->mobile;
            $employee->department   = $request->department;
            $employee->designation  = $request->designation;
            $employee->designation  = $request->designation;
            $employee->dob          = date("Y-m-d", strtotime($request->dob));
            $employee->joining_date = date("Y-m-d", strtotime($request->joining_date));
            $employee->exit_date    = !empty($request->exit_date) ? date("Y-m-d", strtotime($request->exit_date)) : '';
            $employee->salary_type  = $request->salary_type;
            $employee->salary       = $request->salary;
            $employee->save();

            return redirect()->route('employee.index')->with(
                'success', 'Employee successfully updated.'
            );
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

    }


    public function destroy($id)
    {
        if(\Auth::user()->type == 'company')
        {
            $user = User::find($id);
            $user->delete();

            $employee = Employee::where('user_id', $id)->first();
            $employee->delete();

            return redirect()->route('employee.index')->with('success', __('Employee successfully deleted.'));
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

    }

    function employeeNumber()
    {
        $latest = Employee::where('created_by', '=', \Auth::user()->creatorId())->latest()->first();
        if(!$latest)
        {
            return 1;
        }

        return $latest->employee_id + 1;
    }

    public function json(Request $request)
    {
        $designations = Designation::where('department', $request->department_id)->get()->pluck('name', 'id')->toArray();

        return response()->json($designations);
    }

    public function employeePersonalInfoEdit(Request $request, $id)
    {

        $validator = \Validator::make(
            $request->all(), [
                               'dob' => 'required',
                               'gender' => 'required',
                               'address' => 'required',
                               'mobile' => 'required',
                               'emergency_contact' => 'required',
                               'profile' => 'mimes:jpeg,png,jpg|max:20480',
                           ]
        );

        if($validator->fails())
        {
            $messages = $validator->getMessageBag();

            return redirect()->back()->with('error', $messages->first());
        }

        $user = User::find($id);
        if($request->hasFile('profile'))
        {
            $filenameWithExt = $request->file('profile')->getClientOriginalName();
            $filename        = pathinfo($filenameWithExt, PATHINFO_FILENAME);
            $extension       = $request->file('profile')->getClientOriginalExtension();
            $fileNameToStore = $filename . '_' . time() . '.' . $extension;

            $dir        = storage_path('uploads/avatar/');
            $image_path = $dir . $user->avatar;

            if(\File::exists($image_path))
            {
                \File::delete($image_path);
            }

            if(!file_exists($dir))
            {
                mkdir($dir, 0777, true);
            }

            $path = $request->file('profile')->storeAs('uploads/avatar/', $fileNameToStore);

        }

        if(!empty($request->name))
        {
            $user       = User::find($id);
            $user->name = $request->name;
            if(!empty($request->profile))
            {
                $user->avatar = $fileNameToStore;
            }
            $user->save();
        }

        $employee                    = Employee::where('user_id', $id)->first();
        $employee->gender            = $request->gender;
        $employee->address           = $request->address;
        $employee->mobile            = $request->mobile;
        $employee->emergency_contact = $request->emergency_contact;
        $employee->dob               = date("Y-m-d", strtotime($request->dob));
        $employee->save();

        return redirect()->back()->with(
            'success', 'Employee personal successfully updated.'
        );
    }

    public function employeeCompanyInfoEdit(Request $request, $id)
    {

        $validator = \Validator::make(
            $request->all(), [
                               'department' => 'required',
                               'designation' => 'required',
                               'joining_date' => 'required',
                               'salary_type' => 'required',
                               'salary' => 'required',
                           ]
        );

        if($validator->fails())
        {
            $messages = $validator->getMessageBag();

            return redirect()->back()->with('error', $messages->first());
        }


        if(!empty($request->name))
        {
            $user       = User::find($id);
            $user->name = $request->name;

            $user->save();
        }

        $employee               = Employee::where('user_id', $id)->first();
        $employee->department   = $request->department;
        $employee->designation  = $request->designation;
        $employee->designation  = $request->designation;
        $employee->joining_date = date("Y-m-d", strtotime($request->joining_date));
        $employee->exit_date    = !empty($request->exit_date) ? date("Y-m-d", strtotime($request->exit_date)) : '';
        $employee->salary_type  = $request->salary_type;
        $employee->salary       = $request->salary;
        $employee->save();

        return redirect()->back()->with(
            'success', 'Employee company successfully updated.'
        );

    }

    public function employeeBankInfoEdit(Request $request, $id)
    {

        $validator = \Validator::make(
            $request->all(), [
                               'account_holder_name' => 'required',
                               'account_number' => 'required',
                               'bank_name' => 'required',
                               'bank_identifier_code' => 'required',
                               'branch_location' => 'required',
                           ]
        );

        if($validator->fails())
        {
            $messages = $validator->getMessageBag();

            return redirect()->back()->with('error', $messages->first());
        }


        $employee                       = Employee::where('user_id', $id)->first();
        $employee->account_holder_name  = $request->account_holder_name;
        $employee->account_number       = $request->account_number;
        $employee->bank_name            = $request->bank_name;
        $employee->bank_identifier_code = $request->bank_identifier_code;
        $employee->branch_location      = $request->branch_location;
        $employee->save();

        return redirect()->route('employee.index')->with(
            'success', 'Employee bank detail successfully updated.'
        );

    }
}

