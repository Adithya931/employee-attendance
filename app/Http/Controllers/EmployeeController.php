<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\EmployeeRequest;
use Aws\Rekognition\RekognitionClient;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return response()->json([
            'data'      => Employee::with('attendances')->get(['id', 'employee_id', 'name'])->makeHidden('attendances')
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  EmployeeRequest $request
     * @return \Illuminate\Http\Response
     */
    public function store(EmployeeRequest $request)
    {

        $data = $request->validated();

        DB::beginTransaction();

        try {

            // $file = $request->file('image');

            // $name = $file->getClientOriginalName();
            // $extension = $file->getClientOriginalExtension();

            $path = $request->file('image')->store('employee');

            if (!$path)
                return response()->json([
                    'code'    => 500,
                    'message' => "Couldn't upload Image",
                    'status'   => "error"
                ], 500);

            $inc_no = 0;
            $previous_employee = Employee::orderBy('id', 'desc')->first();
            if ($previous_employee)
                $inc_no = $previous_employee->id;

            $data['image'] = $path;
            $data['employee_id'] = 'EMP';
            $employee = Employee::create($data);

            $employee->employee_id = $employee->employee_id.$employee->id;
            $employee->save();

            $client = new RekognitionClient(config('aws.recognition'));

            $image = fopen($request->file('image')->getPathName(), 'r');
            $bytes = fread($image, $request->file('image')->getSize());

            $client->indexFaces([
                'CollectionId' => "employee.attendance",
                'DetectionAttributes' => [],
                'ExternalImageId' => $employee->employee_id,
                'Image' => [ // REQUIRED
                    'Bytes' => $bytes,
                ],
            ]);

            DB::commit();

            return $employee;
            return response()->json([
                'code' => 200,
                'message' => "Employee Created Successfully!!",
                'status' => "success"
            ], 200);
        } catch (\Exception $ex) {

            DB::rollback();

            return response()->json([
                'code'    => 500,
                'message' => "Something Went Wrong..Please Try Again",
                'error'   => $ex->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  Employee $employee
     * @return \Illuminate\Http\Response
     */
    public function show(Employee $employee)
    {
        return $employee;

        // $client = new RekognitionClient(config('aws.recognition'));
        // return $client->listFaces([
        //     'CollectionId' => 'employee.attendance',
        //     // 'MaxResults' => 20,
        // ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  EmployeeRequest $request
     * @param  int  Employee $employee
     * @return \Illuminate\Http\Response
     */
    public function update(EmployeeRequest $request, Employee $employee)
    {
        $data = $request->validated();

        DB::beginTransaction();

        try {

            $employee->fill($data);
            $employee->save();

            DB::commit();

            return response()->json([
                'code' => 200,
                'message' => "Employee Updated Successfully!!",
                'status' => "success"
            ], 200);
        } catch (\Exception $ex) {

            DB::rollback();

            return response()->json([
                'code'    => 500,
                'message' => "Something Went Wrong..Please Try Again",
                'error'   => $ex->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  Employee $employee
     * @return \Illuminate\Http\Response
     */
    public function destroy(Employee $employee)
    {

        if ($employee->attendances->count())
            return response()->json([
                'code'    => 500,
                'message' => "Can't delete employee with attendance",
                'status'   => "error"
            ], 500);

        DB::beginTransaction();

        try {

            $employee->delete();

            DB::commit();

            return response()->json([
                'code' => 200,
                'message' => "Employee Deleted Successfully!!",
                'status' => "success"
            ], 200);
        } catch (\Exception $ex) {

            DB::rollback();

            report($ex);

            return response()->json([
                'code'    => 500,
                'message' => "Something Went Wrong..Please Try Again",
                'error'   => $ex->getMessage()
            ], 500);
        }
    }
}
