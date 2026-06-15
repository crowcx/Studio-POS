<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;

class BookingController extends Controller
{
    // Menampilkan form booking
    public function create()
    {
        $packages = $this->getPackageOptions();
        return view('booking.create', compact('packages'));
    }

    // Menyimpan booking baru
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'studio_category' => 'required|in:family_graduation,prewedding_indoor,studio_outdoor,sewa_event,custom',
            'package_type' => 'required|string|max:255',
            'booking_date' => 'required|date',
            'number_of_people' => 'required|integer|min:1',
            'down_payment' => 'required|numeric|min:0',
            'payment_method' => 'required|in:Cash,Transfer,QRIS',
            'notes' => 'nullable|string',
        ]);

        // Hitung harga paket dengan breakdown
        $priceData = $this->calculatePackagePriceWithBreakdown(
            $validated['studio_category'],
            $validated['package_type'],
            $validated['number_of_people']
        );

        $basePackagePrice = $priceData['base_price'];
        $additionalCharge = $priceData['additional_charge'];
        $packagePrice = $priceData['total_price'];

        // Hitung total amount
        $totalAmount = $packagePrice;
        
        // Hitung remaining amount
        $remainingAmount = $totalAmount - $validated['down_payment'];

        // Buat booking
        $booking = Booking::create([
            'booking_code' => Booking::generateBookingCode(),
            'user_id' => Auth::id(),
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'],
            'studio_category' => $validated['studio_category'],
            'package_type' => $validated['package_type'],
            'package_price' => $packagePrice,
            'base_package_price' => $basePackagePrice,
            'additional_charge' => $additionalCharge,
            'booking_date' => $validated['booking_date'],
            'number_of_people' => $validated['number_of_people'],
            'down_payment' => $validated['down_payment'],
            'payment_method' => $validated['payment_method'],
            'total_amount' => $totalAmount,
            'remaining_amount' => $remainingAmount,
            'notes' => $validated['notes'],
            'status' => 'pending',
        ]);

        \App\Models\AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'booking added',
            'details' => $booking->toArray()
        ]);

        return redirect()->route('booking.index')->with('success', 'Booking berhasil dibuat!');
    }

    // Menampilkan daftar booking
    public function index(Request $request)
    {
        $query = Booking::with('user');

        // Filter: Pencarian (Nama Pelanggan & Kode Booking)
        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                  ->orWhere('booking_code', 'like', "%{$search}%");
            });
        }

        // Filter: Kategori Studio
        if ($request->has('category') && $request->category != '') {
            $query->where('studio_category', $request->category);
        }

        // Filter: Status
        if ($request->has('status') && $request->status != '') {
            $query->where('status', $request->status);
        }

        // Filter: Bulan dan Tahun
        if ($request->has('month') && $request->month != '') {
            $query->whereMonth('booking_date', $request->month);
        }
        if ($request->has('year') && $request->year != '') {
            $query->whereYear('booking_date', $request->year);
        }

        // Sorting
        $sortField = $request->input('sort', 'created_at');
        $sortDirection = $request->input('direction', 'desc');
        
        $allowedSorts = ['customer_name', 'studio_category', 'package_type', 'booking_date', 'created_at'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->latest();
        }

        $bookings = $query->paginate(15)->withQueryString();
        
        return view('booking.index', compact('bookings'));
    }

    // Menampilkan detail booking
    public function show(Booking $booking)
    {
        return view('booking.show', compact('booking'));
    }

    // Menampilkan form edit booking
    public function edit(Booking $booking)
    {
        // Cegah edit booking yang sudah selesai
        if ($booking->status === 'completed') {
            return redirect()->route('booking.show', $booking)->with('error', 'Booking yang sudah selesai tidak dapat diedit.');
        }
        
        $packages = $this->getPackageOptions();
        return view('booking.edit', compact('booking', 'packages'));
    }

    // Update booking
    public function update(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'studio_category' => 'required|in:family_graduation,prewedding_indoor,studio_outdoor,sewa_event,custom',
            'package_type' => 'required|string|max:255',
            'booking_date' => 'required|date',
            'number_of_people' => 'required|integer|min:1',
            'down_payment' => 'required|numeric|min:0',
            'payment_method' => 'required|in:Cash,Transfer,QRIS',
            'notes' => 'nullable|string',
        ]);

        // Hitung harga paket baru dengan breakdown
        $priceData = $this->calculatePackagePriceWithBreakdown(
            $validated['studio_category'],
            $validated['package_type'],
            $validated['number_of_people']
        );

        $basePackagePrice = $priceData['base_price'];
        $additionalCharge = $priceData['additional_charge'];
        $packagePrice = $priceData['total_price'];

        // Hitung total amount baru
        $totalAmount = $packagePrice;
        
        // Hitung remaining amount baru
        $remainingAmount = $totalAmount - $validated['down_payment'];

        // Update booking
        $before = $booking->toArray();
        $booking->update([
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'],
            'studio_category' => $validated['studio_category'],
            'package_type' => $validated['package_type'],
            'package_price' => $packagePrice,
            'base_package_price' => $basePackagePrice,
            'additional_charge' => $additionalCharge,
            'booking_date' => $validated['booking_date'],
            'number_of_people' => $validated['number_of_people'],
            'down_payment' => $validated['down_payment'],
            'payment_method' => $validated['payment_method'],
            'total_amount' => $totalAmount,
            'remaining_amount' => $remainingAmount,
            'notes' => $validated['notes'],
        ]);

        \App\Models\AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'booking edited',
            'details' => [
                'before' => $before,
                'after' => $booking->fresh()->toArray()
            ]
        ]);

        return redirect()->route('booking.index')->with('success', 'Booking berhasil diperbarui!');
    }

    // Hapus booking (dengan refund)
    public function destroy(Booking $booking)
    {
        // Jika booking sudah ada DP, catatan refund perlu dibuat
        if ($booking->down_payment > 0) {
            // Log refund disini (bisa ditambahkan ke tabel refunds atau catatan khusus)
            // Untuk sekarang hanya menghapus booking
        }

        \App\Models\AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'booking deleted',
            'details' => $booking->toArray()
        ]);

        $booking->delete();
        return redirect()->route('booking.index')->with('success', 'Booking berhasil dihapus!');
    }

    // Mark as done (hanya selesaikan booking)
    public function markAsDone(Booking $booking)
    {
        // Murni hanya menyelesaikan status booking, karena data akan di-input oleh kasir sendiri via 'Manajemen Produk'
        $booking->update([
            'status' => 'completed',
        ]);

        \App\Models\AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'booking completed',
            'details' => $booking->toArray()
        ]);

        return redirect()->route('booking.index')->with('success', 'Booking telah diselesaikan!');
    }

    // Menampilkan invoice dalam iframe
    public function invoice(Booking $booking)
    {
        return view('booking.invoice', compact('booking'));
    }

    // Helper method untuk mendapatkan opsi paket
    private function getPackageOptions()
    {
        return [
            'family_graduation' => [
                'Paket 1 250k (max 5 orang)',
                'Paket 2 450k (max 10 orang)', 
                'Paket 3 750k (max 15 orang)',
            ],
            'prewedding_indoor' => [
                'Paket 1 350k',
                'Paket 2 500k',
                'Paket 3 850k',
            ],
            'studio_outdoor' => [
                'Paket Lite (1.250k)',
                'Paket Pro (1.750k)',
            ],
            'sewa_event' => [
                'Bronze (1.750k)',
                'Silver (2.750k)',
                'Gold (4.250k)',
            ],
            'custom' => [
                'Custom Price',
            ],
        ];
    }

    // Helper method untuk menghitung harga paket
    private function calculatePackagePrice($category, $packageType, $numberOfPeople)
    {
        $price = 0;

        switch ($category) {
            case 'family_graduation':
                if ($packageType === 'Paket 1 250k (max 5 orang)') {
                    $price = 250000;
                } elseif ($packageType === 'Paket 2 450k (max 10 orang)') {
                    $price = 450000;
                } elseif ($packageType === 'Paket 3 750k (max 15 orang)') {
                    $price = 750000;
                    // Additional charge jika melebihi 15 orang
                    if ($numberOfPeople > 15) {
                        $additionalPeople = $numberOfPeople - 15;
                        $price += $additionalPeople * 50000;
                    }
                }
                break;

            case 'prewedding_indoor':
                if ($packageType === 'Paket 1 350k') {
                    $price = 350000;
                } elseif ($packageType === 'Paket 2 500k') {
                    $price = 500000;
                } elseif ($packageType === 'Paket 3 850k') {
                    $price = 850000;
                }
                break;

            case 'studio_outdoor':
                if ($packageType === 'Paket Lite (1.250k)') {
                    $price = 1250000;
                } elseif ($packageType === 'Paket Pro (1.750k)') {
                    $price = 1750000;
                }
                break;

            case 'sewa_event':
                if ($packageType === 'Bronze (1.750k)') {
                    $price = 1750000;
                } elseif ($packageType === 'Silver (2.750k)') {
                    $price = 2750000;
                } elseif ($packageType === 'Gold (4.250k)') {
                    $price = 4250000;
                }
                break;

            case 'custom':
                // Untuk custom, harga diinput manual di form
                $price = request()->input('custom_price', 0);
                break;
        }

        return $price;
    }

    // Helper method untuk menghitung harga paket dengan breakdown
    private function calculatePackagePriceWithBreakdown($category, $packageType, $numberOfPeople)
    {
        $basePrice = 0;
        $additionalCharge = 0;
        $totalPrice = 0;

        switch ($category) {
            case 'family_graduation':
                if ($packageType === 'Paket 1 250k (max 5 orang)') {
                    $basePrice = 250000;
                } elseif ($packageType === 'Paket 2 450k (max 10 orang)') {
                    $basePrice = 450000;
                } elseif ($packageType === 'Paket 3 750k (max 15 orang)') {
                    $basePrice = 750000;
                    // Additional charge jika melebihi 15 orang
                    if ($numberOfPeople > 15) {
                        $additionalPeople = $numberOfPeople - 15;
                        $additionalCharge = $additionalPeople * 50000;
                    }
                }
                break;

            case 'prewedding_indoor':
                if ($packageType === 'Paket 1 350k') {
                    $basePrice = 350000;
                } elseif ($packageType === 'Paket 2 500k') {
                    $basePrice = 500000;
                } elseif ($packageType === 'Paket 3 850k') {
                    $basePrice = 850000;
                }
                break;

            case 'studio_outdoor':
                if ($packageType === 'Paket Lite (1.250k)') {
                    $basePrice = 1250000;
                } elseif ($packageType === 'Paket Pro (1.750k)') {
                    $basePrice = 1750000;
                }
                break;

            case 'sewa_event':
                if ($packageType === 'Bronze (1.750k)') {
                    $basePrice = 1750000;
                } elseif ($packageType === 'Silver (2.750k)') {
                    $basePrice = 2750000;
                } elseif ($packageType === 'Gold (4.250k)') {
                    $basePrice = 4250000;
                }
                break;

            case 'custom':
                // Untuk custom, harga diinput manual di form
                $basePrice = request()->input('custom_price', 0);
                break;
        }

        $totalPrice = $basePrice + $additionalCharge;

        return [
            'base_price' => $basePrice,
            'additional_charge' => $additionalCharge,
            'total_price' => $totalPrice,
        ];
    }
}
