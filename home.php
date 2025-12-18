<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>COACH: College Outreach and Academic Collaboration Hub</title>
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/home.css">
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
                    <li><a href="home.php">About Us</a></li>
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

    <!-- Best Section -->
    <section class="main fade-in">
        <video autoplay loop muted plays-inline class="bg-video">
            <source src="uploads/img/CodeVid3.mp4" type="video/mp4">
        </video>
        <div class="left">
            <h3 class="typing-gradient">
                <span class="typed-text">Online Learning</span>
              </h3>
            <div class="header-container">
                <div class="text-content">
                    <h1>Connecting Aspiring Tech Learners with Expert Coaches</h1>
                </div>
                <img src="uploads/img/code.gif" alt="Coding Animation" class="laptop-right">
            </div>
            <img src="uploads/img/Trusted.png" alt="Trusted" class="trusted-right">
        </div>
        <div class="right">
            <img src="uploads/img/right.png" alt="">
        </div>
        
    </section>

    <!-- Slider Banner Section -->
    <section class="slider-container">
        <div class="slider">
            <div class="slide">
                <img src="uploads/img/Slide1.png" alt="Slide 1">
            </div>
            <div class="slide">
                <img src="uploads/img/Slide2.png" alt="Slide 2">
            </div>
            <div class="slide">
                <img src="uploads/img/Slide3.png" alt="Slide 3">
            </div>
        </div>
        <button class="prev" onclick="moveSlide(-1)">&#10094;</button>
        <button class="next" onclick="moveSlide(1)">&#10095;</button>
    </section>

    <section class="info1 fade-in">
        <div class="second">
            <h3>Traditional learning is no longer enough</h3>
            <div class="second-container">
                <img src="uploads/img/girl3d.png" alt="Girl" class="girl-left"> <!-- Image on the left -->
                <div class="second-content">
                    <h1>
                        Aspiring tech learners need personalized, hands-on mentorship to thrive in today’s fast-paced digital world. 
                        <br><br>
                        COACH bridges the gap by connecting BPSU students with experienced tech mentors who provide tailored guidance, real-world industry insights, and on-demand support. Through this mentorship platform, learners gain the skills and confidence needed to excel in the tech industry. 
                        <br><br>
                        Empower your future with expert mentorship.
                    </h1>
                </div>
            </div>
        </div>
    </section>

    <section class="info2 fade-in">
        <div class="third">
            <h3>What is COACH?</h3>
            <div class="third-container">
                <div class="third-content">
                    <h1>COACH is a mentoring platform that connects BPSU students with experienced tech professionals, bridging the gap between education and industry through personalized guidance and real-world insights.</h1>
                </div>
                <img src="uploads/img/coach3d.png" alt="Coach" class="coach-right">
            </div>
        </div>
        <div class="right1">
            <img src="uploads/img/right1.png" alt="">
        </div>
    </section>

    <section class="how-it-works fade-in">
        <div class="how-container">
          <div class="how-header">
            <h2>How it Works?</h2>
            <p>COACH A serves as a bridge between tech learners and the industry, offering access to experienced mentors who provide real-world insights, personalized guidance, and career-building support. The platform helps learners grow their skills, explore opportunities, and confidently step into the tech world..</p>
          </div>
      
          <div class="how-cards">
            <div class="how-card">
              <div class="how-icon">🎯</div>
              <h3>Discover & Join</h3>
              <p>Set up your profile and explore a network of expert mentors tailored to your aspirations, learning style, and tech preferences.

              </p>
            </div>
      
            <div class="how-card">
              <div class="how-icon">💬</div>
              <h3>Connect & Learn</h3>
              <p>Build strong mentor relationships, ask questions, share your projects, and gain valuable industry knowledge and hands-on tips from experienced professionals.

              </p>
            </div>
      
            <div class="how-card">
              <div class="how-icon">🚀</div>
              <h3>Grow & Succeed</h3>
              <p>Track your development, boost your confidence, and prepare for internships or full-time roles in the tech industry—with guidance every step of the way.</p>
            </div>
          </div>
        </div>
      </section>

    <section class="tutoring-benefits fade-in">
        <h2>Benefits of Our Online Tutoring Service</h2>
        <div class="benefits-cards">
          <div class="bcard">
            <div class="icon blue">👥</div>
            <h3>Personalized Mentorship</h3>
            <p>Connect with experienced mentors who understand your tech goals and can guide you with tailored advice and encouragement every step of the way.</p>
           
          </div>
          <div class="bcard">
            <div class="icon yellow">💸</div>
            <h3>No Cost to Learn</h3>
            <p>Learning should be accessible. COACH A offers mentorship for free—so you can focus on growing, not on paying.</p>
           
          </div>
          <div class="bcard">
            <div class="icon red">📊</div>
            <h3>Real Impact</h3>
            <p>Our mentees gain practical skills, boost their confidence, and see measurable growth in knowledge, project quality, and job readiness.</p>
            
          </div>
          <div class="bcard">
            <div class="icon green">⏰</div>
            <h3>Support Anytime</h3>
            <p>Mentors are available around your schedule to offer help, guidance, or feedback—whenever you need it.</p>
            
          </div>
        </div>
      </section>



    <section class="featured-courses fade-in">
        <div class="section-header">
          <small>LEARNING PROGRAM</small>
          <h2>Featured Program</h2>
          <p class="description">
            Explore our featured program, thoughtfully designed to enrich knowledge, foster innovation, and support academic and professional growth.
          </p>
        </div>
      
        <div class="course-grid">
          <div class="course-box">
            <img src="uploads/img/IT.png" alt="IT">
            <p>INFORMATION TECHNOLOGY</p>
          </div>
          <div class="course-box">
            <img src="uploads/img/CS.png" alt="CS">
            <p>COMPUTER SCIENCE</p>
          </div>
          <div class="course-box">
            <img src="uploads/img/DS.png" alt="DAT">
            <p>DATA SCIENCE</p>
          </div>
          <div class="course-box">
            <img src="uploads/img/GD.png" alt="GD">
            <p>GAME DEVELOPMENT</p>
          </div>
          <div class="course-box">
            <img src="uploads/img/DAT.png" alt="DAT">
            <p>DIGITAL ANIMATION TECHNOLOGY</p>
          </div>
        </div>
      
        <div class="see-more-wrapper">
          <a href="courses.php" class="see-more-btn">See More</a>
        </div>
      </section>

      <section class="mentor-section fade-in">
        <div class="mentor-container">
          <div class="mentor-images">
            <img src="uploads/img/girllaptop3d.png" alt="Mentor at laptop" class="mentor-img">
          </div>
          <div class="mentor-content">
            <h2>
              Be a <span class="highlight-purple">Mentor</span> with <strong>COACH</strong>
            </h2>
            <p>
              Becoming a mentor with COACH means more than just sharing knowledge—it’s about making a lasting impact. Help tech learners navigate their paths, overcome challenges, and gain the confidence they need to succeed in the industry. Whether you're offering coding tips, career advice, or industry insights, your experience could change someone's future.
            </p>
            <ul class="mentor-benefits">
              <li><span class="green-dot">●</span> Guide Aspiring Tech Talent</li>
              <li><span class="green-dot">●</span> Give Back to the Community</li>
              <li><span class="green-dot">●</span> Enhance Your Leadership Skills</li>
              <li><span class="green-dot">●</span> Be a Part of Something Meaningful</li>
            </ul>
            <a href="signup_mentor.php"><button class="mentor-btn">Apply as Mentor →</button></a>
          </div>
        </div>
      </section>

      <section class="choose-coach-section fade-in">
        <div class="choose-coach-header">
          <h4>WHY STUDENTS TRUST US</h4>
          <h2>Why Choose <span>COACH</span></h2>
          <p>We connect tech learners with seasoned mentors, turning classroom knowledge into real-world skills and meaningful tech careers.</p>
        </div>
      
        <div class="choose-coach-cards">
          <div class="choose-card">
            <h3>Tailored Mentorship</h3>
            <p>Get matched with mentors who understand your journey. Sessions are customized to your skills, goals, and growth path.</p>

          </div>
      
          <div class="choose-card highlight-card">
            <h3>Hands-On Industry Experience</h3>
            <p>Gain insights from real-world tech professionals. Learn industry practices, tools, and strategies that boost your career readiness.</p>

          </div>
      
          <div class="choose-card">
            <h3>Flexible & Accessible</h3>
            <p>Connect anytime, from anywhere. COACH A is completely free and works around your schedule to support your learning.</p>
 
          </div>
        </div>
      </section>

          <section class="feedback-section fade-in">
    <div class="feedback-container">
        <h2 class="section-title">FEEDBACK & REVIEWS</h2>
        <h3 class="section-subtitle">WHAT OUR MENTEES SAY</h3>

        <div class="reviews-grid">
            <div class="review-card">
                <div class="reviewer-info">
                    <img src="uploads/img/mentee1.jpg" alt="Max Kabunak" class="reviewer-avatar">
                    <div class="reviewer-details">
                        <p class="reviewer-name">Rovic Dellomo</p>
                        <p class="reviewer-title">Mentee</p>
                    </div>
                </div>
              <p class="review-text">The coach provides <strong>masterful strategy and clear accountability.</strong> It's the most powerful system for results-driven performance. A true game-changer!</p>
                <div class="stars">
                    <span class="star">&#9733;</span>
                    <span class="star">&#9733;</span>
                    <span class="star">&#9733;</span>
                    <span class="star">&#9733;</span>
                    <span class="star">&#9733;</span>
                </div>
            </div>

            <div class="review-card featured">
                <div class="reviewer-info">
                    <img src="uploads/img/mentee2.jpg" alt="Max Kabunak" class="reviewer-avatar">
                    <div class="reviewer-details">
                        <p class="reviewer-name">Mark Joseph Miranda</p>
                        <p class="reviewer-title">Mentee</p>
                    </div>
                </div>
              <p class="review-text">The coach's strategy is <strong>pure momentum.</strong> You get <strong>immediate clarity</strong> and <strong>unprecedented results.</strong></p>
                   <div class="stars"> 
                    <span class="star">&#9733;</span>
                    <span class="star">&#9733;</span>
                    <span class="star">&#9733;</span>
                    <span class="star">&#9733;</span>
                    <span class="star">&#9733;</span>
                </div>
            </div>

            <div class="review-card">
                <div class="reviewer-info">
                    <img src="uploads/img/mentee3.jpg" alt="Max Kabunak" class="reviewer-avatar">
                    <div class="reviewer-details">
                        <p class="reviewer-name">Justin Dawn Calimbas</p>
                        <p class="reviewer-title">Mentee</p>
                    </div>
                </div>
               <p class="review-text">The coaching is a <strong>direct fast-track to execution</strong>. They provide the strategy, accountability, and measurable results needed for success.</p>
                <div class="stars">
                    <span class="star">&#9733;</span>
                    <span class="star">&#9733;</span>
                    <span class="star">&#9733;</span>
                    <span class="star">&#9733;</span>
                    <span class="star">&#9733;</span>
                </div>
            </div>
        </div>
    </div>
</section>

      <section class="trusted-section fade-in">
        <div class="trusted-container">
         
          <div class="logo-row">
            <img src="uploads/img/bpsuwhite.png" alt="BPSU Logo" />
            <img src="uploads/img/ccstwhite.png" alt="CCST Logo" />
            <img src="uploads/img/archwhite.png" alt="Archwizard Logo" />
           
          </div>
        </div>
      </section>

      <section class="partnerships-section fade-in">
        <div class="section-header">
          <h2>Our Partnerships</h2>
          <p>
            We’re proud to collaborate with organizations that empower learning, creativity, and innovation. Together, we create meaningful impact.
          </p>
        </div>
        <div class="partner-cards">
          <div class="partner-card">
            <img src="uploads/img/bpsu.png" alt="BPSU Logo">
            <h3>BPSU</h3>
            <p>The home university of our student developers, BPSU nurtures innovation, leadership, and academic excellence across disciplines.

            </p>
          </div>
          <div class="partner-card">
            <img src="uploads/img/ccst.png" alt="CCST Logo">
            <h3>CCST</h3>
            <p>Home to the dedicated students who conceptualized and developed the COACH platform, turning innovation into action through real-world tech solutions.</p>
          </div>
          <div class="partner-card">
            <img src="uploads/img/archwiz.png" alt="ARCHWIZ Logo">
            <h3>ARCHWIZ</h3>
            <p>Serving as a creative moderator, Archwizard facilitates engaging sessions, manages digital content, and ensures smooth, interactive experiences for both mentors and students.</p>
          </div>
        </div>
      </section>

       <section class="team-section">
      <div class="team-header">
        <p class="subtitle">OUR TEAM</p>
        <h2>Meet With Our <span>Team</span></h2>
        <p class="description"> We are a passionate team of developers and innovators, working together to build impactful solutions through creativity and collaboration.</p>
      </div>

     <div class="team-cards">
  <div class="team-card" data-description="Mikaela, as a Systems Developer, drives innovation through system analysis and development. She enjoys turning ideas into functional solutions that improve efficiency and impact.">
    <img src="uploads/img/mikaela.png" alt="Mikaela Nicole Cando">
    <h3>Mikaela Nicole Cando</h3>
    <p class="position">SYSTEMS DEVELOPER</p>
  </div>

  <div class="team-card" data-description="Ken, our UI/UX Developer, brings creativity and technical expertise together to design engaging user interfaces. He ensures software projects are intuitive, practical, and visually appealing.">
    <img src="uploads/img/kenneth.jpg" alt="John Kenneth Dizon">
    <h3>John Kenneth <br>Dizon</h3> 
    <p class="position">SYSTEMS DEVELOPER</p>
  </div>

  <div class="team-card" data-description="Angela, also a Systems Developer, excels in balancing technical solutions with strategic planning. She focuses on building reliable systems while guiding the team with her leadership.">
    <img src="uploads/img/angela.PNG" alt="Angela Marie Gabriel">
    <h3>Angela Marie Gabriel</h3>
    <p class="position">SYSTEMS DEVELOPER</p>
  </div>

  <div class="team-card" data-description="Kengie, our Project Documenter, ensures every detail of the project is well-documented and organized. His role is vital in maintaining clarity, accuracy, and consistency across all technical work.">
    <img src="uploads/img/kengie.jpg" alt="Kengie Nicolai Tandoc">
    <h3>Kengie Nicolai Tandoc</h3>
    <p class="position">PROJECT DOCUMENTER</p>
  </div>
  </div>
</section>


      <section class="project-cta fade-in">
        <div class="cta-content">
          <div class="cta-text">
            <h3>Let's Learn Together</h3>
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
    
    <script>
      document.addEventListener("DOMContentLoaded", function () {
    const fadeElements = document.querySelectorAll(".fade-in");

    const observer = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add("show");
                observer.unobserve(entry.target); // Stop observing after effect triggers
            }
        });
    }, { threshold: 0.2 }); // Adjust threshold for effect timing

    fadeElements.forEach(element => observer.observe(element));

    // Slider functionality
    let index = 0;
    let autoSlideInterval;

    function moveSlide(direction) {
        const slides = document.querySelectorAll('.slide');
        const totalSlides = slides.length;

        index += direction;
        if (index < 0) {
            index = totalSlides - 1;
        } else if (index >= totalSlides) {
            index = 0;
        }

        const slider = document.querySelector('.slider');
        const offset = -index * 100; // Each slide takes up 100% width
        slider.style.transform = `translateX(${offset}%)`;
    }

    // Start the auto slide every 5 seconds
    function startAutoSlide() {
        autoSlideInterval = setInterval(() => {
            moveSlide(1);
        }, 5000);
    }

    // Stop auto slide
    function stopAutoSlide() {
        clearInterval(autoSlideInterval);
    }

    // Auto slide when page loads
    startAutoSlide();

    // Add click event to the next and prev buttons
    const nextButton = document.querySelector('.next');
    const prevButton = document.querySelector('.prev');

    if (nextButton && prevButton) {
        nextButton.addEventListener("click", function () {
            stopAutoSlide(); // Stop auto slide when user clicks
            moveSlide(1); // Move to next slide
            startAutoSlide(); // Restart auto slide after manual interaction
        });

        prevButton.addEventListener("click", function () {
            stopAutoSlide(); // Stop auto slide when user clicks
            moveSlide(-1); // Move to previous slide
            startAutoSlide(); // Restart auto slide after manual interaction
        });
    }
});
    </script>
</body>
</html>
