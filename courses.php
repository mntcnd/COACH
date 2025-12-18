<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>COACH Courses</title>
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/course.css">
    <link rel="stylesheet" href="css/footer.css">
    <link rel="icon" href="uploads/img/coachicon.svg" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet">
</head>
<body>

    <!-- Navigation Section -->
    <section class="background" id="home">
        <nav class="navbar">
            <div class="logo">
                <img src="uploads/img/LogoCoach.png" alt="Logo">
                <span>COACH</span>
            </div>

            <div class="nav-center">
                <ul class="nav_items" id="nav_links">
                    <li><a href="index.php">About Us</a></li>
                    <li><a href="courses.php">Courses</a></li>
                    <li><a href="mentors.php">Mentors</a></li>
                </ul>
            </div>

            <a href="login.php" class="join-us-button">Login</a>

            <div class="nav_menu" id="menu_btn">
                <i class="ri-menu-line"></i>
            </div>
        </nav>
    </section>

    <section class="featured-courses fade-in">
        <div class="section-header">
          <small>Explore Our Course Selection</small>
          <h2>Featured Courses</h2>
          <p class="description">
            Ready to elevate your skills? Dive into our top-tier courses crafted by industry professionals,
            designed to help you grow and succeed at every level.
          </p>
        </div>
      </section>

    <section class="course-section">
        <div class="course-grid">
          <!-- Card 1 -->
          <div class="course-card">
            <img src="uploads/img/image1.jpg" alt="Course Image">
            <div class="course-content">
              <h3>Fundamentals of Computer Hardware & Networking</h3>
              <p class="author">Hardware, Networking</p>
              <div class="course-meta">
                <span>📘 10 Module</span>
                <span>🕒 45 Min / Session</span>
              </div>
            </div>
          </div>
          
          <div class="course-card">
            <img src="uploads/img/image2.jpg" alt="Course Image">
            <div class="course-content">
              <h3>Introduction to Web Development: HTML, CSS & JavaScript</h3>
              <p class="author">Web Development</p>
              <div class="course-meta">
                <span>📘 14 Module</span>
                <span>🕒 60 Min / Session</span>
              </div>
            </div>
          </div>
          
          <div class="course-card">
            <img src="uploads/img/image3.jpg" alt="Course Image">
            <div class="course-content">
              <h3>Basic Database Management with MySQL</h3>
              <p class="author">Databases</p>
              <div class="course-meta">
                <span>📘 11 Module</span>
                <span>🕒 40 Min / Session</span>
              </div>
            </div>
          </div>
          
          <div class="course-card">
            <img src="uploads/img/image4.jpg" alt="Course Image">
            <div class="course-content">
              <h3>Introduction to Computer Programming Concepts</h3>
              <p class="author">Programming Basics</p>
              <div class="course-meta">
                <span>📘 8 Module</span>
                <span>🕒 55 Min / Session</span>
              </div>
            </div>
          </div>
          
          <div class="course-card">
            <img src="uploads/img/image5.jpg" alt="Course Image">
            <div class="course-content">
              <h3>IT Essentials: Operating Systems & Troubleshooting</h3>
              <p class="author">IT Support</p>
              <div class="course-meta">
                <span>📘 13 Module</span>
                <span>🕒 50 Min / Session</span>
              </div>
            </div>
          </div>
          
          <div class="course-card">
            <img src="uploads/img/image6.jpg" alt="Course Image">
            <div class="course-content">
              <h3>Cybersecurity Basics & Online Safety</h3>
              <p class="author">Cybersecurity</p>
              <div class="course-meta">
                <span>📘 9 Module</span>
                <span>🕒 35 Min / Session</span>
              </div>
            </div>
          </div>
          
          <div class="course-card">
            <img src="uploads/img/image7.jpg" alt="Course Image">
            <div class="course-content">
              <h3>Mobile App Development for Beginners</h3>
              <p class="author">Mobile Development</p>
              <div class="course-meta">
                <span>📘 12 Module</span>
                <span>🕒 60 Min / Session</span>
              </div>
            </div>
          </div>
          
          <div class="course-card">
            <img src="uploads/img/image8.jpg" alt="Course Image">
            <div class="course-content">
              <h3>Introduction to Cloud Computing</h3>
              <p class="author">Cloud Technologies</p>
              <div class="course-meta">
                <span>📘 7 Module</span>
                <span>🕒 42 Min / Session</span>
              </div>
            </div>
          </div>

        </section>

        <div class="wrapper">
          <div class="container">
            <h1>WHO CAN APPLY?</h1>
            <div class="grid">
              <!-- Card 1 -->
              <div class="card">
                <div class="icon">👨🏻‍💻</div>
                <div class="text">
                  <h2>FOR MENTEE</h2>
                  <p>Willing to learn and grow in a technology-related course.</p>
                </div>
                <button class="info-btn" onclick="openPopup('popup1')">i</button>
              </div>
        
              <!-- Card 2 -->
              <div class="card">
                <div class="icon">👨🏻‍💼</div>
                <div class="text">
                  <h2>FOR MENTOR</h2>
                  <p>Dedicated to guiding others and committed to a strong academic performance.</p>
                </div>
                <button class="info-btn" onclick="openPopup('popup2')">i</button>
              </div>
        
              <!-- Card 3 -->
              <div class="card">
                <div class="icon">💻</div>
                <div class="text">
                  <h2>Access to Devices</h2>
                  <p>Must have access to a smartphone, tablet, or computer for participation.</p>
                </div>
              </div>
        
              <!-- Card 4 -->
              <div class="card">
                <div class="icon">💡</div>
                <div class="text">
                  <h2>Respectful Attitude</h2>
                  <p>Must show respect and professionalism toward fellow mentees and mentors.</p>
                </div>
              </div>
        
              <!-- Card 5 -->
              <div class="card">
                <div class="icon">🙋🏻‍♂️</div>
                <div class="text">
                  <h2>Age for Mentee</h2>
                  <p>Must be within the age range specified for mentee eligibility in technology-related courses.</p></p></p>
                </div>
                <button class="info-btn" onclick="openPopup('popup3')">i</button>
              </div>
        
              <!-- Card 6 -->
              <div class="card">
                <div class="icon">👩🏻‍🏫</div>
                <div class="text">
                  <h2>Age for Mentor</h2>
                  <p>Must be within the age range specified for mentor eligibility in technology-related fields.</p>
                </div>
                <button class="info-btn" onclick="openPopup('popup4')">i</button>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Popup 1: Regular 2nd Year Student -->
        <div class="popup" id="popup1">
          <div class="popup-content">
            <span class="close" onclick="closePopup('popup1')">&times;</span>
            <p><strong>FOR MENTEE:</strong> Eager to learn and expand knowledge in technology-related fields, and open to developing new skills through guidance and collaboration.</p>
          </div>
        </div>
        
        <!-- Popup 2: General Weighted Average -->
        <div class="popup" id="popup2">
          <div class="popup-content">
            <span class="close" onclick="closePopup('popup2')">&times;</span>
            <p><strong>FOR MENTOR:</strong> Dedicated to guiding others and committed to maintaining strong academic performance and a passion for teaching and mentoring in technology-related fields.</p>
          </div>
        </div>
        
        <!-- Popup 3: Did Not Qualify -->
        <div class="popup" id="popup3">
          <div class="popup-content">
            <span class="close" onclick="closePopup('popup3')">&times;</span>
            <p>Must be 12 years old or above, with the ability and willingness to learn about technology-related concepts.</p>
          </div>
        </div>
        
        <!-- Popup 4: Did Not Avail -->
        <div class="popup" id="popup4">
          <div class="popup-content">
            <span class="close" onclick="closePopup('popup4')">&times;</span>
            <p>Students can be mentors as long as they have a strong academic background and a passion for guiding others in technology-related fields, and are within the specified age range for eligibility.</p>
          </div>
        </div>
        
        <!-- JS for Popups -->
        <script>
          function openPopup(id) {
            document.getElementById(id).style.display = "flex";
          }
        
          function closePopup(id) {
            document.getElementById(id).style.display = "none";
          }
        
          // Optional: close popup on outside click
          window.onclick = function(event) {
            const popups = document.querySelectorAll('.popup');
            popups.forEach(popup => {
              if (event.target === popup) {
                popup.style.display = "none";
              }
            });
          };
        </script>
        

        <section class="project-cta fade-in">
            <div class="cta-content">
              <div class="cta-text">
                <h3>Let's Learn Together Now</h3>
                <p>
                  Ready to explore, grow, and achieve more—together? Join our learning journey and unlock your full potential with a supportive community.
                </p>
              </div>
              <div class="cta-button">
                <a href="login.php" class="btn-outline">Get Started</a>
              </div>
            </div>
          </section>


          <footer class="footer fade-in">
            <div class="footer-container">
              <div class="footer-section about">
                <h2 class="logo">COACH</h2>
                <p>COACH connects tech learners with industry mentors, empowering future tech leaders through personalized guidance, real-world insights, and hands-on collaboration.</p>
              </div>
        
              <div class="footer-section office">
                <h2>Location</h2>
                <p>BPSU - Main<br>Balanga, Bataan<br>Tenejero Capitol Drive 2100, Philippines</p>
                <p>Email: <a href="mailto:avinashdm@outlook.com">coachtech@gmail.com</a></p>
                <p>Phone: +63 - 9666592022</p>
              </div>
        
              <div class="footer-section links">
                <h2>Terms and Privacy</h2>
                <ul>
                  <li><a href="#">About Coach</a></li>
                  <li><a href="#">FAQs</a></li>
                  <li><a href="#">Privacy Policy</a></li>
                  <li><a href="#">Terms of Use</a></li>
                  <li><a href="#">Code of Conduct</a></li>
                </ul>
              </div>
        
              <div class="footer-section newsletter">
                <h2>Subscription</h2>
                <form action="#">
                  <div class="email-box">
                    <i class="fas fa-envelope"></i>
                    <input type="email" placeholder="Enter your email id" required>
                    <button type="submit">Submit<i class="fas fa-arrow-right"></i></button>
                  </div>
                </form>
                <div class="social-icons">
                  <a href="#"><i class="fab fa-facebook-f"></i></a>
                  <a href="#"><i class="fab fa-twitter"></i></a>
                  <a href="#"><i class="fab fa-whatsapp"></i></a>
                  <a href="#"><i class="fab fa-pinterest"></i></a>
                </div>
              </div>
            </div>
        
            <div class="footer-bottom">
              <p>© 2025-2026 COACH. All rights reserved</p>
            </div>
          </footer>
    



</body>
</html>
