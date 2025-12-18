document.addEventListener('DOMContentLoaded', () => {
    // --- Element Selection ---
    const names = document.querySelector(".names");
    const email = document.querySelector(".email");
    const joined = document.querySelector(".joined");
    const navBar = document.querySelector("nav");
    const navToggle = document.querySelector(".navToggle");
    const navLinks = document.querySelectorAll(".navList");
    const darkToggle = document.querySelector(".darkToggle");
    const body = document.querySelector("body");

    const homeContent = document.getElementById("homeContent");
    const addCourseSection = document.getElementById("addCourseSection");
    const courseTitle = document.getElementById("courseTitle");
    const submittedCoursesTitle = document.getElementById("submittedCoursesTitle");
    const submittedCourses = document.getElementById("submittedCourses");
    const sessionsContent = document.getElementById("sessionsContent");
    const forumContent = document.getElementById("forumContent");
    const resourceLibraryContent = document.getElementById("resourceLibraryContent");
    const applicationsContent = document.getElementById("applicationsContent");

    // --- Function to Update Visible Sections ---
    function updateVisibleSections() {
        const activeLink = document.querySelector(".navList.active");
        const activeText = activeLink ? activeLink.querySelector("span")?.textContent.trim() : null;

        // Hide all sections
        if (homeContent) homeContent.style.display = "none";
        if (addCourseSection) addCourseSection.style.display = "none";
        if (courseTitle) courseTitle.style.display = "none";
        if (submittedCoursesTitle) submittedCoursesTitle.style.display = "none";
        if (submittedCourses) submittedCourses.style.display = "none";
        if (sessionsContent) sessionsContent.style.display = "none";
        if (forumContent) forumContent.style.display = "none";
        if (resourceLibraryContent) resourceLibraryContent.style.display = "none";
        if (applicationsContent) applicationsContent.style.display = "none";

        // Show based on active
        switch (activeText) {
            case "Home":
                if (homeContent) homeContent.style.display = "block";
                break;
            case "Courses":
                if (addCourseSection) addCourseSection.style.display = "flex";
                if (courseTitle) courseTitle.style.display = "block";
                if (submittedCoursesTitle) submittedCoursesTitle.style.display = "block";
                if (submittedCourses) submittedCourses.style.display = "flex";
                break;
            case "Sessions":
                if (sessionsContent) sessionsContent.style.display = "block";
                break;
            case "Forum":
                if (forumContent) forumContent.style.display = "block";
                break;
            case "Resource Library":
                if (resourceLibraryContent) resourceLibraryContent.style.display = "block";
                break;
            case "Applications":
                if (applicationsContent) applicationsContent.style.display = "block";
                break;
            default:
                if (homeContent) homeContent.style.display = "block";
                console.warn("No content section defined for active link:", activeText);
        }
    }

    // --- Modal Logic ---
    function openEditResourceModal(resourceID, resourceTitle, resourceType, uploadedBy = '') {
        document.getElementById('editResourceID').value = resourceID;
        document.getElementById('editResourceTitle').value = resourceTitle;
        document.getElementById('editResourceType').value = resourceType;

        // Set the new mentor and uploader values
        document.getElementById('editUploadedBy').value = uploadedBy;

        document.getElementById('editResourceModal').style.display = 'flex';
    }

    function closeEditResourceModal() {
        document.getElementById('editResourceModal').style.display = 'none';
    }

    document.getElementById('editResourceModal').style.display = 'none';

    // --- Navbar Toggle ---
    if (navToggle && navBar) {
        navToggle.addEventListener('click', () => {
            navBar.classList.toggle('close');
        });
    }

    // --- Dark Mode ---
    if (darkToggle && body) {
        darkToggle.addEventListener('click', () => {
            body.classList.toggle('dark');
            if (body.classList.contains('dark')) {
                localStorage.setItem('darkMode', 'enabled');
            } else {
                localStorage.removeItem('darkMode');
            }
        });
        if (localStorage.getItem('darkMode') === 'enabled') {
            body.classList.add('dark');
        }
    }

    // --- Nav Link Clicks ---
    if (navLinks.length > 0) {
        navLinks.forEach((element) => {
            element.addEventListener('click', function (event) {
                event.preventDefault();
                navLinks.forEach((e) => e.classList.remove('active'));
                this.classList.add('active');
                updateVisibleSections();
            });
        });
    }

    updateVisibleSections(); // On load

    // --- Data Fetching Example ---
    fetch("./data.json")
        .then(response => {
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            return response.json();
        })
        .then(data => {
            if (data && Array.isArray(data.item)) {
                let nameHtml = "", emailHtml = "", joinedHtml = "";
                data.item.forEach(element => {
                    nameHtml += `<span class="data-list">${element.name || ''}</span>`;
                    emailHtml += `<span class="data-list">${element.email || ''}</span>`;
                    joinedHtml += `<span class="data-list">${element.joined || ''}</span>`;
                });
                if (names) names.innerHTML += nameHtml;
                if (email) email.innerHTML += emailHtml;
                if (joined) joined.innerHTML += joinedHtml;
            } else {
                console.warn("Data does not contain expected 'item' array.");
            }
        })
        .catch(error => {
            console.error("Error fetching data.json:", error);
        });

    // --- Edit Button Click Handler ---
    document.body.addEventListener('click', function (e) {
        if (e.target && e.target.matches('.edit-btn')) {
            const resourceID = e.target.getAttribute('data-resource-id');
            const resourceTitle = e.target.getAttribute('data-resource-title');
            const resourceType = e.target.getAttribute('data-resource-type');
            const uploadedBy = e.target.getAttribute('data-uploaded-by') || '';

            openEditResourceModal(resourceID, resourceTitle, resourceType, uploadedBy);
        }
    });
});
