<?php
// Start session and check if the user is logged in
session_start();

if (!isset($_SESSION['user_id'])) {
    // Redirect to login page if the user is not logged in
    header('Location: login.php');
    exit;
}

// Include necessary files (DB connection, header, footer)
include '../includes/db.php';
include '../includes/header.php';
include '../includes/auth.php';

// Check if the user is an admin, if so, redirect them to the admin dashboard
if ($_SESSION['role'] === 'admin') {
    header('Location: admin.php');
    exit;
}

// Get the logged-in user ID
$user_id = $_SESSION['user_id'];
$user_name=$_SESSION['username'];

// Fetch user's exchange requests from the database
$requests_query = "SELECT * FROM exchange_requests WHERE user_id = '$user_id' ORDER BY created_at DESC";
$requests_result = $conn->query($requests_query);
// Count exchange requests by status
$status_counts_query = "
    SELECT 
        status, 
        COUNT(*) as count 
    FROM exchange_requests 
    WHERE user_id = '$user_id' 
    GROUP BY status";
$status_counts_result = $conn->query($status_counts_query);

// Initialize counts
$status_counts = [
    'Pending' => 0,
    'Approved' => 0,
    'Rejected' => 0
];

while ($row = $status_counts_result->fetch_assoc()) {
    $status_counts[$row['status']] = $row['count'];
}

// Fetch total number of transactions
$total_transactions_query = "
SELECT COUNT(*) as total 
FROM exchange_requests 
WHERE user_id = '$user_id'";
$total_transactions_result = $conn->query($total_transactions_query);

// Default total transactions to 0
$total_transactions = 0;

if ($row = $total_transactions_result->fetch_assoc()) {
    $total_transactions = $row['total'];
}

// Fetch exchange rates for INR to FRW and FRW to INR
$exchange_rates_query = "SELECT from_currency, to_currency, rate FROM exchange_rates WHERE (from_currency = 'INR' AND to_currency = 'FRW') OR (from_currency = 'FRW' AND to_currency = 'INR')";
$exchange_rates_result = $conn->query($exchange_rates_query);

// Initialize exchange rate variables
$inr_to_frw_rate = 0;
$frw_to_inr_rate = 0;

while ($rate_row = $exchange_rates_result->fetch_assoc()) {
    if ($rate_row['from_currency'] == 'INR' && $rate_row['to_currency'] == 'FRW') {
        $inr_to_frw_rate = $rate_row['rate'];
    }
    if ($rate_row['from_currency'] == 'FRW' && $rate_row['to_currency'] == 'INR') {
        $frw_to_inr_rate = $rate_row['rate'];
    }
}

$conn->close();
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Dashboard Content -->
<main class="container mx-auto p-4">

    <h1 class="text-3xl font-semibold text-center mb-8">Your Dashboard</h1>
    
    <!-- Transaction Tips Section -->
    <section class="md:p-10 w-full my-40 md:my-2">
    <h1 class="text-3xl font-bold mb-6 text-center">Welcome <?php echo $user_name; ?> !</h1>
        <h2 class="text-2xl font-bold mb-4 text-center md:text-left">Transactions Overview</h2>
        <div class="grid md:grid-cols-4 gap-4 text-center">
            <div class="bg-blue-200 p-6 rounded shadow text-blue-700">
                <h3 class="text-lg font-bold">Total Transactions</h3>
                <p class="text-3xl font-semibold"><?php echo $total_transactions; ?></p>
            </div>
            <div class="bg-green-300 p-6 rounded shadow text-green-700">
                <h3 class="text-lg font-bold">Approved</h3>
                <p class="text-3xl font-semibold "><?php echo $status_counts['Approved']; ?></p>
            </div>
            <div class="bg-yellow-100 p-6 rounded shadow text-yellow-700">
                <h3 class="text-lg font-bold">Pending</h3>
                <p class="text-3xl font-semibold"><?php echo $status_counts['Pending']; ?></p>
            </div>
            <div class="bg-red-300 p-6 rounded shadow text-red-600">
                <h3 class="text-lg font-bold">Rejected</h3>
                <p class="text-3xl font-semibold"><?php echo $status_counts['Rejected']; ?></p>
            </div>
        </div>
    </section>

    <!-- Exchange Rates Section -->
    <section class="mb-8">
        <h2 class="text-2xl font-bold mb-4">Current Exchange Rates</h2>
        <div class="grid grid-cols-2 gap-4 text-center">
            <div class="bg-blue-100 p-6 rounded shadow">
                <h3 class="text-lg font-bold">INR to FRW</h3>
                <p class="text-3xl font-semibold"><?php echo number_format($inr_to_frw_rate, 2); ?></p>
            </div>
            <div class="bg-green-100 p-6 rounded shadow">
                <h3 class="text-lg font-bold">FRW to INR</h3>
                <p class="text-3xl font-semibold"><?php echo number_format($frw_to_inr_rate, 2); ?></p>
            </div>
           
        </div>
    </section>

    
    <section class="mb-8 grid md:grid-cols-2">
        <div class="convert">
            <?php include "../includes/convert.php"; ?>
        
        </div>
        <!-- Exchange Requests Overview (Chart Section) -->
        <div class="md:w-full md:h-full justify-items-center p-2 rounded shadow-md">
        <h2 class="text-2xl font-bold mb-4">Exchange Requests Overview</h2>
            <canvas id="statusPolarChart" class=""></canvas>
        </div>
    </section>

    <!-- New Exchange Request Section -->
    <section class="w-full ">
        <h2 class="text-2xl font-bold mb-4">Make a New Exchange Request</h2>
        <form action="submit_request.php" method="POST" class="space-y-4 bg-white p-6 rounded shadow-md" enctype="multipart/form-data">
            <div class="flex space-x-4">
                <!-- From Currency -->
                <div class="w-1/2">
                    <label for="from_currency" class="block text-sm font-medium text-gray-700">From Currency</label>
                    <select name="from_currency" id="from_currency" class="w-full border-gray-300 rounded-md border p-2 font-bold" required>
                    <option value="FRW">FRW</option>    
                    <option value="INR">INR</option>
                        
                        <!-- Add more currencies here -->
                    </select>
                </div>

                <!-- To Currency -->
                <div class="w-1/2">
                    <label for="to_currency" class="block text-sm font-medium text-gray-700">To Currency</label>
                    <select name="to_currency" id="to_currency" class="w-full border-gray-300 rounded-md border p-2 font-bold" required>
                        <option value="INR">INR</option>
                        <option value="FRW">FRW</option>
                        <!-- Add more currencies here -->
                    </select>
                </div>
            </div>

            <!-- Amount -->
            <div>
                <label for="amount" class="block text-sm font-medium text-gray-700">Amount</label>
                <input type="number" name="amount" id="amount" class="w-full border-gray-300 rounded-md border p-2 font-bold" required>
            </div>

            <!-- Payment Method -->
            <div>
                <label for="payment_method" class="block text-sm font-medium text-gray-700">Payment Method</label>
                <select name="payment_method" id="payment_method" class="w-full border-gray-300 rounded-md p-2 border font-bold" required>
                <option value="" disabled>Choose Payment method</option>
                <option value="Bank">Bank Transfer</option>
                    <option value="Mobile money">Mobile Money</option>
                    <!-- Add more payment methods here -->
                </select>
            </div>
            <div>
            <label for="amount" class="block text-sm font-medium text-gray-700">Payment Number</label>
            <input type="text" name="pay_number" id="amount" class="w-full border-gray-300 rounded-md border p-2 font-bold" required>
            </div>

            <!-- Payment Screenshot -->
            <div>
                <label for="payment_screenshot" class="block text-sm font-medium text-gray-700">Payment Screenshot</label>
                <input type="file" name="payment_screenshot" id="payment_screenshot" class="w-full border-gray-300 rounded-md p-2 border" accept="image/*" required>
            </div>

            <!-- Hidden status field (default to 'Pending') -->
            <input type="hidden" name="status" value="Pending">

            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 w-full">Submit Request</button>
        </form>
    </section>
    <!-- Your Previous Exchange Requests Section -->
    <section class="p-4">
    <h2 class="text-2xl font-bold mb-4">Previous Requests</h2>
    <?php if ($requests_result->num_rows > 0): ?>
        <div class="overflow-x-auto shadow-lg rounded border border-gray-200">
            <table class="min-w-full bg-white">
                <thead>
                    <tr class="bg-gray-100 text-gray-700 text-left">
                        <th class="px-4 py-3 border font-semibold">From Currency</th>
                        <th class="px-4 py-3 border font-semibold">To Currency</th>
                        <th class="px-4 py-3 border font-semibold">Amount</th>
                        <th class="px-4 py-3 border font-semibold">Payment M & Account N</th>
                        <th class="px-4 py-3 border font-semibold">Status</th>
                        <th class="px-4 py-3 border font-semibold">Date</th>
                        <th class="px-4 py-3 border font-semibold">Admin Screenshot</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($request = $requests_result->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 border text-sm text-gray-700"><?php echo $request['from_currency']; ?></td>
                            <td class="px-4 py-3 border text-sm text-gray-700"><?php echo $request['to_currency']; ?></td>
                            <td class="px-4 py-3 border text-sm text-gray-700"><?php echo $request['amount']; ?></td>
                            <td class="px-4 py-3 border text-sm text-gray-700"><?php echo $request['payment_method'];?> :<span class="font-bold text-green-700 p-1 border rounded-md"> <?php echo $request['payment_number'];?></span> </td>
                            <td class="px-4 py-3 border text-sm text-gray-700">
                                <span class="px-2 py-1 rounded-full <?php echo $request['status'] === 'Completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                    <?php echo $request['status']; ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 border text-sm text-gray-700"><?php echo $request['created_at']; ?></td>
                            <td class="px-4 py-3 border text-sm text-gray-700 text-center">
                                <?php if (!empty($request['admin_screenshot'])): ?>
                                    <a href="uploads/payment_screenshots/<?php echo htmlspecialchars($request['admin_screenshot']); ?>" target="_blank" title="View Full Image">
                                        <img src="uploads/payment_screenshots/<?php echo htmlspecialchars($request['admin_screenshot']); ?>" alt="Admin Screenshot" class="w-16 h-16 rounded shadow">
                                    </a>
                                <?php else: ?>
                                    <p class="text-center text-red-600 font-bold">▲ : Wait screenshot!</p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-center text-gray-500 mt-4">You have no exchange requests yet.</p>
    <?php endif; ?>
</section>



</main>
<?php include '../includes/footer.php'; ?>

<script>
    // Data for the Polar Area Chart
    const statusData = {
        labels: ['Pending', 'Approved', 'Rejected'],
        datasets: [{
            data: [
                <?php echo $status_counts['Pending']; ?>,
                <?php echo $status_counts['Approved']; ?>,
                <?php echo $status_counts['Rejected']; ?>
            ],
            backgroundColor: [
                'rgba(255, 205, 86, 0.7)', // Pending - Yellow
                'rgba(75, 192, 192, 0.7)', // Approved - Green
                'rgba(255, 99, 132, 0.7)'  // Rejected - Red
            ],
            borderColor: [
                'rgba(255, 205, 86, 1)',
                'rgba(75, 192, 192, 1)',
                'rgba(255, 99, 132, 1)'
            ],
            borderWidth: 1
        }]
    };

    // Polar Area Chart Configuration
    const config = {
        type: 'polarArea',
        data: statusData,
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right'
                }
            }
        }
    };

    // Render the Chart
    const ctx = document.getElementById('statusPolarChart').getContext('2d');
    new Chart(ctx, config);
</script>

