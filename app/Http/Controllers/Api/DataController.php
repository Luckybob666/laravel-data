<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DataRecord;
use App\Models\UploadRecord;
use App\Models\ActivityLog;
use App\Jobs\ProcessFileUpload;
use App\Jobs\ProcessFileDownload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class DataController extends Controller
{
    /**
     * 上传文件并处理数据
     */
    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,csv|max:10240', // 10MB
            'country' => 'nullable|string|max:255',
            'industry' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('file');
            $originalFilename = $file->getClientOriginalName();
            $filename = time() . '_' . $originalFilename;
            $filePath = 'uploads/' . $filename;

            // 存储文件
            Storage::disk('public')->put($filePath, file_get_contents($file->getRealPath()));

            // 创建上传记录
            $uploadRecord = UploadRecord::create([
                'filename' => $filename,
                'original_filename' => $originalFilename,
                'file_path' => $filePath,
                'user_id' => auth()->id(),
                'status' => 'pending',
            ]);

            // 分发队列任务
            ProcessFileUpload::dispatch($uploadRecord, 'refined');

            // 记录活动日志
            ActivityLog::log(
                'upload',
                "API上传精数据文件：{$originalFilename}",
                [
                    'upload_record_id' => $uploadRecord->id,
                    'filename' => $originalFilename,
                    'data_type' => 'refined',
                ],
                $uploadRecord
            );

            return response()->json([
                'success' => true,
                'message' => '文件上传成功，正在后台处理中',
                'data' => [
                    'upload_record_id' => $uploadRecord->id,
                    'filename' => $originalFilename,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '上传失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取数据记录列表
     */
    public function index(Request $request)
    {
        $query = DataRecord::query();

        // 应用过滤器
        if ($request->filled('phone')) {
            $query->where('phone', 'like', '%' . $request->phone . '%');
        }

        if ($request->filled('country')) {
            $query->where('country', 'like', '%' . $request->country . '%');
        }

        if ($request->filled('industry')) {
            $query->where('industry', 'like', '%' . $request->industry . '%');
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $records = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $records
        ]);
    }

    /**
     * 更新数据记录
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'country' => 'nullable|string|max:255',
            'industry' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $record = DataRecord::findOrFail($id);
            $record->update($request->only(['country', 'industry', 'remarks']));

            // 记录活动日志
            ActivityLog::log(
                'update',
                "API更新数据记录：{$record->phone}",
                [
                    'record_id' => $record->id,
                    'phone' => $record->phone,
                ],
                $record
            );

            return response()->json([
                'success' => true,
                'message' => '更新成功',
                'data' => $record
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '更新失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 删除数据记录
     */
    public function destroy($id)
    {
        try {
            $record = DataRecord::findOrFail($id);
            $phone = $record->phone;
            $record->delete();

            // 记录活动日志
            ActivityLog::log(
                'delete',
                "API删除数据记录：{$phone}",
                [
                    'phone' => $phone,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => '删除成功'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '删除失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 导出数据
     */
    public function export(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'format' => 'required|in:xlsx,csv',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $filters = $request->only(['phone', 'country', 'industry', 'date_from', 'date_to']);
            
            // 分发导出任务
            ProcessFileDownload::dispatch($filters, $request->format, auth()->id());

            // 记录活动日志
            ActivityLog::log(
                'download',
                "API导出数据，格式：{$request->format}",
                [
                    'format' => $request->format,
                    'filters' => $filters,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => '导出任务已添加到队列，请稍后查看下载文件'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '导出失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取上传记录列表
     */
    public function uploadRecords(Request $request)
    {
        $query = UploadRecord::with('user');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $records = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $records
        ]);
    }

    /**
     * 获取活动日志列表
     */
    public function activityLogs(Request $request)
    {
        $query = ActivityLog::with('user');

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
    }
}
