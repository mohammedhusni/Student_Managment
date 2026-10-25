
// StudentHub - JavaScript functionality

document.addEventListener("DOMContentLoaded", function () {

    // 1. Animated heading text
    const animatedText = document.getElementById("animatedText");

    const phrases = [
        "efficiently.",
        "with confidence.",
        "in one place.",
        "with ease."
    ];

    let phraseIndex = 0;

    if (animatedText) {
        setInterval(function () {
            phraseIndex = (phraseIndex + 1) % phrases.length;

            animatedText.style.opacity = "0";

            setTimeout(function () {
                animatedText.textContent = phrases[phraseIndex];
                animatedText.style.opacity = "1";
            }, 300);

        }, 2500);
    }

    // 2. Search student records
    const searchInput = document.getElementById("searchInput");
    const table = document.getElementById("studentsTable");
    const noResults = document.getElementById("noSearchResults");

    if (searchInput && table && noResults) {
        searchInput.addEventListener("input", function () {

            const query = searchInput.value
                .trim()
                .toLowerCase();

            const rows = table.querySelectorAll("tbody tr");
            let visibleCount = 0;

            rows.forEach(function (row) {

                if (row.id === "emptyRow") {
                    return;
                }

                const text = row.textContent.toLowerCase();
                const matches = text.includes(query);

                row.hidden = !matches;

                if (matches) {
                    visibleCount++;
                }
            });

            noResults.hidden =
                query === "" || visibleCount > 0 ||
                rows.length === 0;

        });
    }

    // 3. Confirm before deleting a student
    const deleteForms = document.querySelectorAll(".delete-form");

    deleteForms.forEach(function (form) {
        form.addEventListener("submit", function (event) {

            const confirmed = window.confirm(
                "Are you sure you want to delete this student?"
            );

            if (!confirmed) {
                event.preventDefault();
            }
        });
    });

    // 4. Validate the student form in the browser
    const studentForm = document.getElementById("studentForm");

    if (studentForm) {
        studentForm.addEventListener("submit", function (event) {

            if (!studentForm.checkValidity()) {
                event.preventDefault();
                studentForm.reportValidity();
            }

        });
    }

});
