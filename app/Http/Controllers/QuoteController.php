<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Client;
use App\Models\Job;
use App\Services\QuotePdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class QuoteController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $this->getCompanyId();
        
        $query = Quote::where('company_id', $companyId)->with('client', 'items');
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }
        
        // Filter by client
        if ($request->has('client_id')) {
            $query->where('client_id', $request->input('client_id'));
        }
        
        // Search
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->whereHas('client', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            })->orWhere('notes', 'like', "%{$search}%");
        }
        
        // Sort
        $sortBy = $request->input('sortBy', 'created_at');
        $sortDirection = $request->input('sortDirection', 'desc');
        $query->orderBy($sortBy, $sortDirection);
        
        $perPage = $request->input('limit', 10);
        $quotes = $query->paginate($perPage);
        
        return $this->paginated($quotes->items(), [
            'page' => $quotes->currentPage(),
            'limit' => $quotes->perPage(),
            'total' => $quotes->total(),
            'totalPages' => $quotes->lastPage(),
        ]);
    }

    public function store(Request $request)
    {
        $companyId = $this->getCompanyId();
        
        $validator = Validator::make($request->all(), [
            'client_id' => 'required|uuid|exists:clients,id',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'valid_until' => 'required|date|after:today',
            'notes' => 'nullable|string',
            'profit_margin' => 'nullable|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation error', $validator->errors(), 422);
        }

        // Verify client belongs to company
        $client = Client::where('company_id', $companyId)
            ->findOrFail($request->input('client_id'));

        DB::beginTransaction();
        try {
            // Calculate totals
            $subtotal = 0;
            $taxAmount = 0;
            
            foreach ($request->input('items') as $item) {
                $quantity = $item['quantity'];
                $unitPrice = $item['unit_price'];
                $taxRate = $item['tax_rate'] ?? 0;
                
                $lineTotal = $quantity * $unitPrice;
                $lineTax = $lineTotal * ($taxRate / 100);
                
                $subtotal += $lineTotal;
                $taxAmount += $lineTax;
            }
            
            $total = $subtotal + $taxAmount;
            $profitMargin = $request->input('profit_margin', 0);
            
            // Create quote
            $quote = Quote::create([
                'company_id' => $companyId,
                'client_id' => $client->id,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'profit_margin' => $profitMargin,
                'status' => 'draft',
                'valid_until' => $request->input('valid_until'),
                'notes' => $request->input('notes'),
            ]);
            
            // Create quote items
            foreach ($request->input('items') as $itemData) {
                $quantity = $itemData['quantity'];
                $unitPrice = $itemData['unit_price'];
                $taxRate = $itemData['tax_rate'] ?? 0;
                $lineTotal = $quantity * $unitPrice;
                
                QuoteItem::create([
                    'quote_id' => $quote->id,
                    'description' => $itemData['description'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'tax_rate' => $taxRate,
                    'line_total' => $lineTotal,
                ]);
            }
            
            DB::commit();
            
            return $this->success(
                $quote->fresh()->load('client', 'items')->toArray(),
                'Quote created successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create quote: ' . $e->getMessage(), null, 500);
        }
    }

    public function show($id)
    {
        $companyId = $this->getCompanyId();
        $quote = Quote::where('company_id', $companyId)->with('client', 'items')->findOrFail($id);
        
        return $this->success($quote->toArray());
    }

    public function update(Request $request, $id)
    {
        $companyId = $this->getCompanyId();
        $quote = Quote::where('company_id', $companyId)->findOrFail($id);
        
        // Don't allow updates to accepted or rejected quotes
        if (in_array($quote->status, ['accepted', 'rejected'])) {
            return $this->error('Cannot update accepted or rejected quotes', null, 422);
        }
        
        $validator = Validator::make($request->all(), [
            'items' => 'sometimes|array|min:1',
            'items.*.description' => 'required_with:items|string|max:255',
            'items.*.quantity' => 'required_with:items|numeric|min:0.01',
            'items.*.unit_price' => 'required_with:items|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'valid_until' => 'sometimes|date|after:today',
            'notes' => 'nullable|string',
            'profit_margin' => 'nullable|numeric|min:0|max:100',
            'status' => 'sometimes|in:draft,sent,accepted,rejected,expired',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation error', $validator->errors(), 422);
        }

        DB::beginTransaction();
        try {
            // Update quote items if provided
            if ($request->has('items')) {
                // Delete existing items
                $quote->items()->delete();
                
                // Calculate new totals
                $subtotal = 0;
                $taxAmount = 0;
                
                foreach ($request->input('items') as $item) {
                    $quantity = $item['quantity'];
                    $unitPrice = $item['unit_price'];
                    $taxRate = $item['tax_rate'] ?? 0;
                    
                    $lineTotal = $quantity * $unitPrice;
                    $lineTax = $lineTotal * ($taxRate / 100);
                    
                    $subtotal += $lineTotal;
                    $taxAmount += $lineTax;
                    
                    // Create new item
                    QuoteItem::create([
                        'quote_id' => $quote->id,
                        'description' => $item['description'],
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'tax_rate' => $taxRate,
                        'line_total' => $lineTotal,
                    ]);
                }
                
                $total = $subtotal + $taxAmount;
                
                // Update quote totals
                $quote->subtotal = $subtotal;
                $quote->tax_amount = $taxAmount;
                $quote->total = $total;
            }
            
            // Update other fields
            if ($request->has('valid_until')) {
                $quote->valid_until = $request->input('valid_until');
            }
            
            if ($request->has('notes')) {
                $quote->notes = $request->input('notes');
            }
            
            if ($request->has('profit_margin')) {
                $quote->profit_margin = $request->input('profit_margin');
            }
            
            if ($request->has('status')) {
                $quote->status = $request->input('status');
            }
            
            $quote->save();
            
            DB::commit();
            
            return $this->success(
                $quote->fresh()->load('client', 'items')->toArray(),
                'Quote updated successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to update quote: ' . $e->getMessage(), null, 500);
        }
    }

    public function destroy($id)
    {
        $companyId = $this->getCompanyId();
        $quote = Quote::where('company_id', $companyId)->findOrFail($id);
        $quote->delete();
        
        return $this->success(null, 'Quote deleted successfully');
    }

    public function send($id)
    {
        $companyId = $this->getCompanyId();
        $quote = Quote::where('company_id', $companyId)->findOrFail($id);
        
        // Only draft or sent quotes can be sent
        if (!in_array($quote->status, ['draft', 'sent'])) {
            return $this->error('Only draft or sent quotes can be sent', null, 422);
        }
        
        $quote->status = 'sent';
        $quote->save();
        
        // TODO: Send email/WhatsApp notification
        // This will be implemented when email integration is added
        
        return $this->success(
            $quote->fresh()->load('client', 'items')->toArray(),
            'Quote sent successfully'
        );
    }

    public function accept($id)
    {
        $companyId = $this->getCompanyId();
        $quote = Quote::where('company_id', $companyId)->findOrFail($id);
        
        // Only sent quotes can be accepted
        if ($quote->status !== 'sent') {
            return $this->error('Only sent quotes can be accepted', null, 422);
        }
        
        // Check if quote is still valid
        if ($quote->valid_until < now()) {
            $quote->status = 'expired';
            $quote->save();
            return $this->error('Quote has expired', null, 422);
        }
        
        $quote->status = 'accepted';
        $quote->save();
        
        return $this->success(
            $quote->fresh()->load('client', 'items')->toArray(),
            'Quote accepted successfully'
        );
    }

    public function reject($id)
    {
        $companyId = $this->getCompanyId();
        $quote = Quote::where('company_id', $companyId)->findOrFail($id);
        
        // Only sent quotes can be rejected
        if ($quote->status !== 'sent') {
            return $this->error('Only sent quotes can be rejected', null, 422);
        }
        
        $quote->status = 'rejected';
        $quote->save();
        
        return $this->success(
            $quote->fresh()->load('client', 'items')->toArray(),
            'Quote rejected successfully'
        );
    }

    /**
     * Convert an accepted quote to a job
     */
    public function convertToJob(Request $request, $id)
    {
        $companyId = $this->getCompanyId();
        $quote = Quote::where('company_id', $companyId)
            ->with('client', 'items')
            ->findOrFail($id);
        
        // Only accepted quotes can be converted to jobs
        if ($quote->status !== 'accepted') {
            return $this->error('Only accepted quotes can be converted to jobs', null, 422);
        }
        
        $validator = Validator::make($request->all(), [
            'scheduled_date' => 'required|date|after:today',
            'assigned_technician' => 'nullable|uuid|exists:users,id',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'estimated_duration' => 'nullable|numeric|min:0',
            'location' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation error', $validator->errors(), 422);
        }

        // Verify technician belongs to company if provided
        if ($request->has('assigned_technician')) {
            $technician = \App\Models\User::where('company_id', $companyId)
                ->findOrFail($request->input('assigned_technician'));
        }

        DB::beginTransaction();
        try {
            // Get client address for location if not provided
            $location = $request->input('location');
            if (!$location && $quote->client->address) {
                $location = $quote->client->address;
            }

            // Create job from quote
            $job = Job::create([
                'company_id' => $companyId,
                'client_id' => $quote->client_id,
                'quote_id' => $quote->id,
                'title' => 'Job from Quote #' . substr($quote->id, 0, 8),
                'description' => $quote->notes ?? 'Job created from accepted quote',
                'status' => 'scheduled',
                'priority' => $request->input('priority', 'medium'),
                'estimated_duration' => $request->input('estimated_duration'),
                'assigned_technician' => $request->input('assigned_technician'),
                'scheduled_date' => $request->input('scheduled_date'),
                'location' => $location ?? [],
                'notes' => $request->input('notes'),
            ]);
            
            DB::commit();
            
            return $this->success(
                $job->fresh()->load('client', 'technician', 'quote')->toArray(),
                'Job created from quote successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create job from quote: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Download quote as PDF
     */
    public function downloadPdf($id)
    {
        $companyId = $this->getCompanyId();
        $quote = Quote::where('company_id', $companyId)->findOrFail($id);
        
        $pdfService = new QuotePdfService();
        $filename = 'quote-' . substr($quote->id, 0, 8) . '.pdf';
        
        return $pdfService->download($quote, $filename);
    }

    /**
     * Stream quote PDF
     */
    public function streamPdf($id)
    {
        $companyId = $this->getCompanyId();
        $quote = Quote::where('company_id', $companyId)->findOrFail($id);
        
        $pdfService = new QuotePdfService();
        
        return $pdfService->stream($quote);
    }
}

