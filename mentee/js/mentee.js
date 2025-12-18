// --- Make performSearch global so HTML button can access it ---
console.log("✅ mentee.js is loaded");

function performSearch() {
  const searchBox = document.getElementById("search-box");
  const searchTerm = searchBox.value.trim();

  if (searchTerm === "") {
    fetchAllResources();
  } else {
    searchResources(searchTerm);
  }
}

window.performSearch = performSearch;

function fetchAllResources() {
  const xhr = new XMLHttpRequest();
  xhr.open("POST", "search_resources.php", true);
  xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

  xhr.onload = function () {
    if (xhr.status === 200) {
      document.getElementById("course-results").innerHTML = xhr.responseText;
    }
  };

  xhr.send("query=");
}

function searchResources(query) {
  const xhr = new XMLHttpRequest();
  xhr.open("POST", "search_resources.php", true);
  xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

  xhr.onload = function () {
    if (xhr.status === 200) {
      document.getElementById("course-results").innerHTML = xhr.responseText;
    } else {
      console.error("Error fetching resources");
    }
  };

  xhr.onerror = function () {
    console.error("AJAX request failed");
  };

  xhr.send("query=" + encodeURIComponent(query));
}

// --- DOM Ready logic ---
document.addEventListener("DOMContentLoaded", function () {
  const profileIcon = document.getElementById("profile-icon");
  const profileMenu = document.getElementById("profile-menu");
  const searchBox = document.getElementById("search-box");
  const cancelLogoutBtn = document.getElementById("cancelLogout");
  const confirmLogoutBtn = document.getElementById("confirmLogoutBtn");

  searchBox.addEventListener("input", performSearch);
  fetchAllResources();

  // Profile toggle
  profileIcon.addEventListener("click", function (e) {
    e.preventDefault();
    profileMenu.classList.toggle("show");
    profileMenu.classList.remove("hide");
  });

  window.addEventListener("click", function (e) {
    if (!profileMenu.contains(e.target) && !profileIcon.contains(e.target)) {
      profileMenu.classList.remove("show");
      profileMenu.classList.add("hide");
    }
  });

  // Section toggling
  const sections = {
    courses: document.getElementById("courses"),
    resourcelibrary: document.getElementById("resourcelibrary"),
  };

  function showSection(key) {
    for (const [name, section] of Object.entries(sections)) {
      if (name === key) {
        section.style.display = "block";
        setTimeout(() => section.classList.add("active"), 10);
      } else {
        section.classList.remove("active");
        setTimeout(() => (section.style.display = "none"), 300);
      }
    }
  }

  showSection("courses"); // Default to showing the courses section

  document.querySelector('a[href="#courses"]')?.addEventListener("click", function (e) {
    e.preventDefault();
    showSection("courses");
  });

  document.querySelector('a[href="#resourcelibrary"]')?.addEventListener("click", function (e) {
    e.preventDefault();
    showSection("resourcelibrary");
  });

  document.addEventListener("click", function (e) {
    const btn = e.target.closest(".view-btn");
    if (btn) {
      const file = btn.getAttribute("data-file");
      const type = btn.getAttribute("data-type")?.toLowerCase();
      console.log("File:", file);
      console.log("Type:", type);
      
      const viewer = document.getElementById("resource-viewer");
      let content = "";
  
      // 🟨 THIS LINE IS THE ISSUE
      if (type === "pdf") {
        content = `<iframe src="uploads/${file}" width="100%" height="600px" style="border: none;"></iframe>`;
      }
  
  
   
    }
  });
  

    // --- Logout Dialog Logic ---
    // Make confirmLogout function globally accessible for the onclick in HTML
    window.confirmLogout = function(e) { 
        if (e) e.preventDefault(); // FIX: Prevent the default anchor behavior (# in URL)
        if (logoutDialog) {
            logoutDialog.style.display = "flex";
        }
    }

    // FIX: Attach event listeners to the dialog buttons after DOM is loaded
    if (cancelLogoutBtn && logoutDialog) {
        cancelLogoutBtn.addEventListener("click", function(e) {
            e.preventDefault(); 
            logoutDialog.style.display = "none";
        });
    }

    if (confirmLogoutBtn) {
        confirmLogoutBtn.addEventListener("click", function(e) {
            e.preventDefault(); 
            // FIX: Use relative path to access logout.php in the parent directory
            window.location.href = "../login.php"; 
        });
    }
});

