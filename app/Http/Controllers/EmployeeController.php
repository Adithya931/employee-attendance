<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\EmployeeRequest;
use Aws\Rekognition\RekognitionClient;
use Illuminate\Support\Facades\Storage;

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

            $client = new RekognitionClient(config('aws.recognition'));

            // return $client->listFaces([
            //     'CollectionId' => "employee.attendance", // REQUIRED
            // ]);

            // return $client->deleteFaces([
            //     'CollectionId' => "employee.attendance", // REQUIRED
            //     'FaceIds' => ["7e9681e7-7c32-4d59-a7ec-8156418c0d6e"], // REQUIRED
            // ]);

            $file = $request->file('image');

            $image = fopen($file->getPathName(), 'r');
            $bytes = fread($image, $file->getSize());

            $result = $client->searchFacesByImage([
                'CollectionId' => "employee.attendance", // REQUIRED
                '`FaceMatchThreshold`' => 90.00,
                'Image' => [ // REQUIRED
                    'Bytes' => $bytes,
                ],
                'MaxFaces' => 1,
            ]);

            if (count($result['FaceMatches']))
                return response()->json([
                    'code'    => 500,
                    'message' => "Employee Image Already Exists!!",
                    'status'   => "error"
                ], 500);

            // return $result;

            $path = $file->storeAs(
                'employee',
                Str::uuid()->toString() . "." . $file->extension()
            );

            if (!$path)
                return response()->json([
                    'code'    => 500,
                    'message' => "Couldn't upload Image",
                    'status'   => "error"
                ], 500);

            $data['image'] = $path;
            $data['employee_id'] = 'EMP';

            $employee = Employee::create($data);

            $employee_id = $employee->employee_id . $employee->id;

            $result = $client->indexFaces([
                'CollectionId' => "employee.attendance",
                'DetectionAttributes' => [],
                'ExternalImageId' => $employee_id,
                'Image' => [ // REQUIRED
                    'Bytes' => $bytes,
                ],
            ]);

            $employee->employee_id = $employee_id;
            $employee->faceId = $result['FaceRecords'][0]['Face']['FaceId'];
            $employee->save();

            DB::commit();

            $employee->image = Storage::temporaryUrl(
                $employee->image,
                now()->addMinutes(30)
            );

            return $employee;
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
        $employee->image = Storage::temporaryUrl(
            $employee->image,
            now()->addMinutes(30)
        );

        return $employee;
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

            $client = new RekognitionClient(config('aws.recognition'));

            $client->deleteFaces([
                'CollectionId' => "employee.attendance", // REQUIRED
                'FaceIds' => [$employee->faceId], // REQUIRED
            ]);

            if (Storage::exists($employee->image))
                Storage::delete($employee->image);

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
