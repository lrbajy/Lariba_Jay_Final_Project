
document.addEventListener('DOMContentLoaded', function () {
    const attendanceForm = document.getElementById('attendanceForm');
    const dateInput = document.getElementById('attendanceDate');
    const tableBody = document.getElementById('attendanceTableBody');
    const messageBox = document.getElementById('attendanceMessage');

    if (!attendanceForm || !dateInput || !tableBody) {
        return;
    }

    function showMessage(message, success = true) {
        if (!messageBox) {
            alert(message);
            return;
        }

        messageBox.textContent = message;
        messageBox.style.display = 'block';
        messageBox.style.color = success ? '#15803d' : '#dc2626';
    }

    async function loadAttendance() {
        try {
            const response = await fetch(
                '../api/attendance.php?date=' +
                encodeURIComponent(dateInput.value),
                {
                    headers: {
                        'Accept': 'application/json'
                    }
                }
            );

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Failed to load attendance.');
            }

            tableBody.innerHTML = '';

            if (result.data.length === 0) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="5">No attendance records found.</td>
                    </tr>
                `;
                return;
            }

            result.data.forEach(function (record) {
                const row = document.createElement('tr');

                const values = [
                    record.student_number,
                    record.first_name + ' ' + record.last_name,
                    record.section,
                    record.status,
                    record.attendance_date
                ];

                values.forEach(function (value) {
                    const cell = document.createElement('td');
                    cell.textContent = value ?? '';
                    row.appendChild(cell);
                });

                tableBody.appendChild(row);
            });

        } catch (error) {
            showMessage(error.message, false);
        }
    }

    attendanceForm.addEventListener('submit', async function (event) {
        event.preventDefault();

        const formData = new FormData(attendanceForm);
        const studentId = formData.get('student_id');
        const status = formData.get('status');

        if (!studentId || !status || !dateInput.value) {
            showMessage('Please select a student, date, and status.', false);
            return;
        }

        try {
            const response = await fetch('../api/attendance.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    student_id: studentId,
                    attendance_date: dateInput.value,
                    status: status
                })
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Failed to save attendance.');
            }

            showMessage(result.message);
            attendanceForm.reset();
            await loadAttendance();

        } catch (error) {
            showMessage(error.message, false);
        }
    });

    dateInput.addEventListener('change', loadAttendance);

    loadAttendance();
});
