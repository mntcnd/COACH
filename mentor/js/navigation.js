document.addEventListener("DOMContentLoaded", function() {
    // Select all necessary elements
    const profileIcon = document.getElementById("profile-icon");
    const profileMenu = document.getElementById("profile-menu");
    
    // Elements for LOGOUT DIALOG
    const logoutDialog = document.getElementById("logoutDialog");
    const cancelLogoutBtn = document.getElementById("cancelLogout");
    const confirmLogoutBtn = document.getElementById("confirmLogoutBtn");

    // 🔑 ELEMENTS FOR LEAVE CHAT DIALOG
    const leaveChatDialog = document.getElementById("leaveChatDialog");
    const cancelLeaveBtn = document.getElementById("cancelLeave");
    const confirmLeaveBtn = document.getElementById("confirmLeaveBtn"); // Confirm button inside the modal
    let leaveChatURL = ''; // Variable to store the URL dynamically

    // Elements for CANCEL SESSION DIALOG
    const cancelSessionDialog = document.getElementById("cancelSessionDialog");
    const cancelSessionBtn = document.getElementById("cancelSession");
    const sessionToCancelIDInput = document.getElementById("sessionToCancelID");


    // --- Profile Menu Toggle Logic (UNCHANGED) ---
    if (profileIcon && profileMenu) {
        profileIcon.addEventListener("click", function (e) {
            e.preventDefault();
            profileMenu.classList.toggle("show");
            profileMenu.classList.toggle("hide");
        });
        
        // Close menu when clicking outside
        document.addEventListener("click", function (e) {
            // Check if the click is outside the icon, the menu, and the menu's sub-elements
            if (!profileIcon.contains(e.target) && !profileMenu.contains(e.target) && !e.target.closest('#profile-menu')) {
                profileMenu.classList.remove("show");
                profileMenu.classList.add("hide");
            }
        });
    }

    // ------------------------------------
    // --- LOGOUT Dialog Logic (UNCHANGED) ---
    // ------------------------------------
    
    if (cancelLogoutBtn && logoutDialog) {
        cancelLogoutBtn.addEventListener("click", function(e) {
            e.preventDefault(); 
            logoutDialog.style.display = "none";
        });
    }

    if (confirmLogoutBtn) {
        confirmLogoutBtn.addEventListener("click", function(e) {
            e.preventDefault(); 
            window.location.href = "../logout.php"; 
        });
    }

    window.confirmLogout = function(e) { 
        if (e) e.preventDefault(); 
        if (logoutDialog) {
            logoutDialog.style.display = "flex";
        }
    }


    // ------------------------------------
    // 🔑 LEAVE CHAT Dialog Logic (UPDATED)
    // ------------------------------------
    
    // Function to show the Leave Chat dialog (called by the link's onclick in forum-chat.php)
    window.confirmLeaveChat = function(e) {
        if (e) e.preventDefault(); 
        
        // 🔑 CRITICAL: Get the URL from the 'data-leave-url' attribute of the clicked link
        const clickedLink = e.target.closest('a');
        if (clickedLink) {
            leaveChatURL = clickedLink.getAttribute('data-leave-url');
        }

        if (leaveChatDialog) {
            leaveChatDialog.style.display = "flex";
        }
    }

    // Attach event listener to the modal's Cancel button
    if (cancelLeaveBtn && leaveChatDialog) {
        cancelLeaveBtn.addEventListener("click", function(e) {
            e.preventDefault(); 
            leaveChatDialog.style.display = "none";
        });
    }

    // 🔑 NEW: Handle the confirmation click inside the modal
    if (confirmLeaveBtn) {
        confirmLeaveBtn.addEventListener("click", function(e) {
            e.preventDefault();
            if (leaveChatURL) {
                // Perform the redirect using the stored URL
                window.location.href = leaveChatURL;
            }
        });
    }


    // ------------------------------------
    // 🔑 CANCEL SESSION Dialog Logic (UNCHANGED)
    // ------------------------------------

    window.confirmCancelSession = function(pendingId, e) {
        if (e) e.preventDefault();
        if (cancelSessionDialog && sessionToCancelIDInput) {
            sessionToCancelIDInput.value = pendingId;
            cancelSessionDialog.style.display = "flex";
        }
    }

    if (cancelSessionBtn && cancelSessionDialog) {
        cancelSessionBtn.addEventListener("click", function(e) {
            e.preventDefault();
            cancelSessionDialog.style.display = "none";
            if (sessionToCancelIDInput) {
                sessionToCancelIDInput.value = 0;
            }
        });
    }

});