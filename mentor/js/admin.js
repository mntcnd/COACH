document.addEventListener('DOMContentLoaded', () => { // Wait for the DOM to be ready

  // --- Element Selection ---
  const names = document.querySelector(".names"); // Make sure these exist in your HTML if used
  const email = document.querySelector(".email"); // Make sure these exist in your HTML if used
  const joined = document.querySelector(".joined"); // Make sure these exist in your HTML if used
  const navBar = document.querySelector("nav");
  const navToggle = document.querySelector(".navToggle");
  const navLinks = document.querySelectorAll(".navList");
  const darkToggle = document.querySelector(".darkToggle");
  const body = document.querySelector("body");

  // Content Sections
  const homeContent = document.getElementById("homeContent"); // Added for Home tab
  const addCourseSection = document.getElementById("addCourseSection");
  const courseTitle = document.getElementById("courseTitle");
  const submittedCoursesTitle = document.getElementById("submittedCoursesTitle");
  const submittedCourses = document.getElementById("submittedCourses");
  const sessionsContent = document.getElementById("sessionsContent"); // Added for Sessions tab
  const forumContent = document.getElementById("forumContent");       // Added for Forum tab
  const resourceLibraryContent = document.getElementById("resourceLibraryContent"); // Added for Resource Library tab
  const applicationsContent = document.getElementById("applicationsContent");     // Added for Applications tab


  // --- Function to Update Visible Sections ---
  function updateVisibleSections() {
      // Find the currently active link
      const activeLink = document.querySelector(".navList.active");
      const activeText = activeLink ? activeLink.querySelector("span")?.textContent.trim() : null;

      // Hide all content sections first
      if (homeContent) homeContent.style.display = "none";
      if (addCourseSection) addCourseSection.style.display = "none";
      if (courseTitle) courseTitle.style.display = "none";
      if (submittedCoursesTitle) submittedCoursesTitle.style.display = "none";
      if (submittedCourses) submittedCourses.style.display = "none";
      if (sessionsContent) sessionsContent.style.display = "none";
      if (forumContent) forumContent.style.display = "none";
      if (resourceLibraryContent) resourceLibraryContent.style.display = "none";
      if (applicationsContent) applicationsContent.style.display = "none";
      // Add any other sections for other tabs here if needed

      // Show sections based on the active tab's text
      switch (activeText) {
          case "Home":
              if (homeContent) homeContent.style.display = "block";
              break;
          case "Courses":
              if (addCourseSection) addCourseSection.style.display = "flex"; // Use flex for the add/preview layout
              if (courseTitle) courseTitle.style.display = "block";
              if (submittedCoursesTitle) submittedCoursesTitle.style.display = "block";
              if (submittedCourses) submittedCourses.style.display = "flex"; // Use flex for the course card layout
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
          // Add cases for other links if they have corresponding content sections
          default:
              // Optional: Show home content if no match or handle error
              if (homeContent) homeContent.style.display = "block";
              console.warn("No content section defined for active link:", activeText);
              break;
      }
  }

  // --- Event Listeners ---

  // Toggle navbar collapse
  if (navToggle && navBar) {
      navToggle.addEventListener('click', () => {
          navBar.classList.toggle('close');
      });
  }

  // Dark mode toggle
  if (darkToggle && body) {
      darkToggle.addEventListener('click', () => {
          body.classList.toggle('dark');
          // Optional: Save preference to localStorage
          if (body.classList.contains('dark')) {
              localStorage.setItem('darkMode', 'enabled');
          } else {
              localStorage.removeItem('darkMode');
          }
      });
      // Optional: Check localStorage on load
      if (localStorage.getItem('darkMode') === 'enabled') {
           body.classList.add('dark');
      }
  }


  // Active link switching & section display
  if (navLinks.length > 0) {
      navLinks.forEach(function(element) {
          element.addEventListener('click', function(event) {

              // Set active state
              navLinks.forEach((e) => e.classList.remove('active'));
              this.classList.add('active');

              // Update visibility based on the *new* active link
              updateVisibleSections();
          });
      });
  }

  // --- Initial Setup ---

  // Set initial section visibility based on the default active link
  updateVisibleSections();

  // --- Data Fetching (Example - keep if used) ---
  // Make sure ./data.json exists and is accessible
  fetch("./data.json")
      .then(response => {
          if (!response.ok) {
              throw new Error(`HTTP error! status: ${response.status}`);
          }
          return response.json();
       })
      .then(data => {
          // Check if 'item' property exists and is an array
          if (data && Array.isArray(data.item)) {
              const items = data.item;
              let nameHtml = "", emailHtml = "", joinedHtml = "";

              items.forEach(element => {
                  // Basic validation/sanitization might be needed depending on data source
                  nameHtml += `<span class="data-list">${element.name || ''}</span>`;
                  emailHtml += `<span class="data-list">${element.email || ''}</span>`;
                  joinedHtml += `<span class="data-list">${element.joined || ''}</span>`;
              });

              // Check if target elements exist before updating
              if (names) names.innerHTML += nameHtml;
              if (email) email.innerHTML += emailHtml;
              if (joined) joined.innerHTML += joinedHtml;
          } else {
               console.warn("Received data does not contain an 'item' array:", data);
          }
      })
      .catch(error => {
          console.error("Error fetching or processing data.json:", error);
           // Optionally display an error message to the user on the page
      });


  // --- Add Course Form Preview Functionality (Removed from here) ---
  // This logic is now included as inline script in coachadmin.php after the relevant HTML elements
  // to ensure elements exist before listeners are attached. If you prefer it here,
  // ensure this entire admin.js script is loaded *after* the HTML body content.


  // --- Submit Logic (Removed - Handled by PHP) ---
  // Your original JS had form submit logic that duplicated PHP.
  // The form submission is handled by PHP page reload/redirection now.
  // The preview logic remains.

}); // End of DOMContentLoaded