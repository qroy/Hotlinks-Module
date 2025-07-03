<?php

namespace Drupal\hotlinks\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Database\Connection;
use Drupal\taxonomy\Entity\Term;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\Component\Utility\Random;

/**
 * Service for generating test data for the Hotlinks module.
 */
class HotlinksTestDataService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Random utility.
   *
   * @var \Drupal\Component\Utility\Random
   */
  protected $random;

  /**
   * Test categories data.
   *
   * @var array
   */
  protected $testCategories = [
    'Technology' => [
      'description' => 'Technology resources and tools for developers and enthusiasts.',
      'subcategories' => [
        'Programming',
        'Web Development',
        'Mobile Development',
        'AI & Machine Learning',
        'DevOps',
        'Open Source',
      ],
    ],
    'Science' => [
      'description' => 'Scientific resources, research, and educational content.',
      'subcategories' => [
        'Physics',
        'Biology',
        'Chemistry',
        'Space & Astronomy',
        'Environmental Science',
      ],
    ],
    'News & Media' => [
      'description' => 'News sources, journalism, and media outlets.',
      'subcategories' => [
        'Tech News',
        'World News',
        'Local News',
        'Podcasts',
        'Magazines',
      ],
    ],
    'Education' => [
      'description' => 'Educational resources, courses, and learning materials.',
      'subcategories' => [
        'Online Courses',
        'Universities',
        'Tutorials',
        'Documentation',
        'Libraries',
      ],
    ],
    'Entertainment' => [
      'description' => 'Entertainment content, media, and leisure activities.',
      'subcategories' => [
        'Movies & TV',
        'Music',
        'Games',
        'Books',
        'Art & Culture',
      ],
    ],
    'Health & Wellness' => [
      'description' => 'Health information, wellness resources, and medical references.',
      'subcategories' => [
        'Medical Resources',
        'Fitness',
        'Nutrition',
        'Mental Health',
      ],
    ],
  ];

  /**
   * Test hotlinks data.
   *
   * @var array
   */
  protected $testHotlinks = [
    // Technology
    [
      'title' => 'GitHub - Where Software is Built',
      'url' => 'https://github.com',
      'description' => 'The world\'s leading platform for version control and collaboration, hosting millions of open source projects.',
      'categories' => ['Technology', 'Programming', 'Open Source'],
    ],
    [
      'title' => 'Stack Overflow - Developer Community',
      'url' => 'https://stackoverflow.com',
      'description' => 'The largest online community for programmers to learn, share knowledge, and advance their careers.',
      'categories' => ['Technology', 'Programming'],
    ],
    [
      'title' => 'MDN Web Docs',
      'url' => 'https://developer.mozilla.org',
      'description' => 'The best resource for web developers, featuring comprehensive documentation for HTML, CSS, and JavaScript.',
      'categories' => ['Technology', 'Web Development', 'Documentation'],
    ],
    [
      'title' => 'TensorFlow - Machine Learning Platform',
      'url' => 'https://tensorflow.org',
      'description' => 'An open-source machine learning framework for building and deploying ML applications.',
      'categories' => ['Technology', 'AI & Machine Learning', 'Open Source'],
    ],
    [
      'title' => 'Docker - Containerization Platform',
      'url' => 'https://docker.com',
      'description' => 'Platform for developing, shipping, and running applications using containerization technology.',
      'categories' => ['Technology', 'DevOps'],
    ],
    [
      'title' => 'React - JavaScript Library',
      'url' => 'https://reactjs.org',
      'description' => 'A JavaScript library for building user interfaces, maintained by Facebook and the community.',
      'categories' => ['Technology', 'Web Development', 'Programming'],
    ],
    [
      'title' => 'Visual Studio Code',
      'url' => 'https://code.visualstudio.com',
      'description' => 'Free, open-source code editor with support for debugging, Git control, and extensions.',
      'categories' => ['Technology', 'Programming', 'Open Source'],
    ],
    [
      'title' => 'Kubernetes Documentation',
      'url' => 'https://kubernetes.io',
      'description' => 'Open-source container orchestration platform for automating deployment and management.',
      'categories' => ['Technology', 'DevOps', 'Open Source'],
    ],

    // Science
    [
      'title' => 'NASA - National Aeronautics and Space Administration',
      'url' => 'https://nasa.gov',
      'description' => 'Official website of NASA with the latest space exploration news, missions, and discoveries.',
      'categories' => ['Science', 'Space & Astronomy'],
    ],
    [
      'title' => 'Nature - International Science Journal',
      'url' => 'https://nature.com',
      'description' => 'Leading international journal publishing the finest peer-reviewed research across all sciences.',
      'categories' => ['Science'],
    ],
    [
      'title' => 'National Geographic',
      'url' => 'https://nationalgeographic.com',
      'description' => 'Explore the world through science, photography, and adventure stories.',
      'categories' => ['Science', 'Environmental Science'],
    ],
    [
      'title' => 'CERN - European Organization for Nuclear Research',
      'url' => 'https://home.cern',
      'description' => 'Home of the Large Hadron Collider and birthplace of the World Wide Web.',
      'categories' => ['Science', 'Physics'],
    ],
    [
      'title' => 'Khan Academy - Free Science Education',
      'url' => 'https://khanacademy.org/science',
      'description' => 'Free, world-class education in biology, chemistry, physics, and more.',
      'categories' => ['Science', 'Education', 'Online Courses'],
    ],

    // News & Media
    [
      'title' => 'BBC News',
      'url' => 'https://bbc.com/news',
      'description' => 'Breaking news, world news, and news about the UK from the BBC.',
      'categories' => ['News & Media', 'World News'],
    ],
    [
      'title' => 'TechCrunch',
      'url' => 'https://techcrunch.com',
      'description' => 'Latest technology news covering startups, gadgets, and internet companies.',
      'categories' => ['News & Media', 'Tech News', 'Technology'],
    ],
    [
      'title' => 'The Verge',
      'url' => 'https://theverge.com',
      'description' => 'Technology, science, art, and culture news with a focus on how technology affects our lives.',
      'categories' => ['News & Media', 'Tech News', 'Technology'],
    ],
    [
      'title' => 'NPR - National Public Radio',
      'url' => 'https://npr.org',
      'description' => 'American public radio with news, podcasts, and cultural programming.',
      'categories' => ['News & Media', 'Podcasts'],
    ],

    // Education
    [
      'title' => 'Coursera - Online Learning Platform',
      'url' => 'https://coursera.org',
      'description' => 'Access courses from top universities and companies worldwide.',
      'categories' => ['Education', 'Online Courses'],
    ],
    [
      'title' => 'MIT OpenCourseWare',
      'url' => 'https://ocw.mit.edu',
      'description' => 'Free access to course materials from the Massachusetts Institute of Technology.',
      'categories' => ['Education', 'Universities', 'Online Courses'],
    ],
    [
      'title' => 'Codecademy',
      'url' => 'https://codecademy.com',
      'description' => 'Interactive platform for learning programming languages and technical skills.',
      'categories' => ['Education', 'Technology', 'Programming', 'Tutorials'],
    ],
    [
      'title' => 'TED-Ed',
      'url' => 'https://ed.ted.com',
      'description' => 'Educational videos and lessons covering a wide range of subjects.',
      'categories' => ['Education', 'Tutorials'],
    ],

    // Entertainment
    [
      'title' => 'Internet Movie Database (IMDb)',
      'url' => 'https://imdb.com',
      'description' => 'The world\'s most popular movie database with ratings, reviews, and trivia.',
      'categories' => ['Entertainment', 'Movies & TV'],
    ],
    [
      'title' => 'Spotify',
      'url' => 'https://spotify.com',
      'description' => 'Digital music streaming service with millions of songs and podcasts.',
      'categories' => ['Entertainment', 'Music'],
    ],
    [
      'title' => 'Steam',
      'url' => 'https://store.steampowered.com',
      'description' => 'Digital distribution platform for PC gaming with thousands of games.',
      'categories' => ['Entertainment', 'Games', 'Technology'],
    ],
    [
      'title' => 'Goodreads',
      'url' => 'https://goodreads.com',
      'description' => 'Social cataloging website for book lovers to track reading and discover new books.',
      'categories' => ['Entertainment', 'Books'],
    ],

    // Health & Wellness
    [
      'title' => 'WebMD',
      'url' => 'https://webmd.com',
      'description' => 'Medical information and health advice you can trust.',
      'categories' => ['Health & Wellness', 'Medical Resources'],
    ],
    [
      'title' => 'MyFitnessPal',
      'url' => 'https://myfitnesspal.com',
      'description' => 'Calorie counting and fitness tracking app for health and wellness goals.',
      'categories' => ['Health & Wellness', 'Fitness', 'Nutrition'],
    ],
    [
      'title' => 'Headspace',
      'url' => 'https://headspace.com',
      'description' => 'Meditation and mindfulness app for mental health and wellbeing.',
      'categories' => ['Health & Wellness', 'Mental Health'],
    ],
  ];

  /**
   * Sample reviews for testing.
   *
   * @var array
   */
  protected $sampleReviews = [
    [
      'text' => 'This is an absolutely essential resource for anyone working in this field. The documentation is comprehensive and always up-to-date. I use this daily in my work and can\'t recommend it highly enough.',
      'rating' => 5,
    ],
    [
      'text' => 'Great resource with lots of useful information. The interface could be improved, but the content quality is excellent. Definitely worth bookmarking.',
      'rating' => 4,
    ],
    [
      'text' => 'Solid resource that covers all the basics well. Good for beginners and has enough depth for more advanced users too. Well organized and easy to navigate.',
      'rating' => 4,
    ],
    [
      'text' => 'This site has been incredibly helpful for my projects. The community is active and supportive. Some sections could use updates, but overall very valuable.',
      'rating' => 4,
    ],
    [
      'text' => 'Decent resource but not as comprehensive as I hoped. Still useful for quick references and basic information. Worth checking out.',
      'rating' => 3,
    ],
    [
      'text' => 'Outstanding platform with excellent tutorials and documentation. The search functionality is particularly good. This has become my go-to resource.',
      'rating' => 5,
    ],
    [
      'text' => 'Very helpful site with good examples and clear explanations. The mobile version works well too. Highly recommended for both beginners and experts.',
      'rating' => 5,
    ],
    [
      'text' => 'Good content overall, though some areas could be better organized. The information is accurate and helpful. A solid addition to any bookmark collection.',
      'rating' => 3,
    ],
    [
      'text' => 'Exceptional quality and attention to detail. This resource has saved me countless hours of research. The team behind this deserves recognition.',
      'rating' => 5,
    ],
    [
      'text' => 'Useful resource with good coverage of the topic. Interface is clean and responsive. Would benefit from more interactive examples.',
      'rating' => 4,
    ],
  ];

  /**
   * Test users for reviews.
   *
   * @var array
   */
  protected $testUsers = [
    'alex_dev',
    'sarah_designer',
    'mike_admin',
    'jenny_student',
    'carlos_researcher',
    'lisa_writer',
    'tom_engineer',
    'emma_scientist',
  ];

  /**
   * Constructs a new HotlinksTestDataService object.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory,
    AccountInterface $current_user,
    Connection $database,
    FileSystemInterface $file_system
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->currentUser = $current_user;
    $this->database = $database;
    $this->fileSystem = $file_system;
    $this->random = new Random();
  }

  /**
   * Generate all test data.
   *
   * @param array $options
   *   Options for data generation:
   *   - categories: bool - Generate test categories (only if they don't exist)
   *   - hotlinks: bool - Generate test hotlinks
   *   - users: bool - Generate test users
   *   - reviews: bool - Generate test reviews (requires hotlinks_reviews)
   *   - thumbnails: bool - Generate placeholder thumbnails
   *
   * @return array
   *   Results of the generation process.
   */
  public function generateTestData(array $options = []) {
    $options += [
      'categories' => TRUE,
      'hotlinks' => TRUE,
      'users' => TRUE,
      'reviews' => TRUE,
      'thumbnails' => TRUE,
    ];

    $results = [
      'categories' => 0,
      'hotlinks' => 0,
      'users' => 0,
      'reviews' => 0,
      'errors' => [],
    ];

    try {
      // Generate categories first (only if they don't exist)
      if ($options['categories']) {
        $results['categories'] = $this->generateTestCategories();
      }

      // Generate test users
      if ($options['users']) {
        $results['users'] = $this->generateTestUsers();
      }

      // Generate hotlinks (this will use ANY existing categories)
      if ($options['hotlinks']) {
        $results['hotlinks'] = $this->generateTestHotlinks($options['thumbnails']);
      }

      // Generate reviews (if module is enabled)
      if ($options['reviews'] && \Drupal::moduleHandler()->moduleExists('hotlinks_reviews')) {
        $results['reviews'] = $this->generateTestReviews();
      }

    } catch (\Exception $e) {
      $results['errors'][] = $e->getMessage();
      \Drupal::logger('hotlinks')->error('Test data generation failed: @error', [
        '@error' => $e->getMessage(),
      ]);
    }

    return $results;
  }

  /**
   * Generate test categories (only if they don't exist).
   *
   * @return int
   *   Number of categories created.
   */
  protected function generateTestCategories() {
    $created_count = 0;

    foreach ($this->testCategories as $parent_name => $category_data) {
      try {
        // Check if category already exists
        $existing = $this->entityTypeManager
          ->getStorage('taxonomy_term')
          ->loadByProperties([
            'vid' => 'hotlink_categories',
            'name' => $parent_name,
          ]);

        if (empty($existing)) {
          // Create parent category
          $parent_term = Term::create([
            'vid' => 'hotlink_categories',
            'name' => $parent_name,
            'description' => [
              'value' => $category_data['description'],
              'format' => 'basic_html',
            ],
          ]);
          $parent_term->save();
          $created_count++;

          // Create subcategories
          foreach ($category_data['subcategories'] as $subcategory_name) {
            $child_term = Term::create([
              'vid' => 'hotlink_categories',
              'name' => $subcategory_name,
              'parent' => $parent_term->id(),
            ]);
            $child_term->save();
            $created_count++;
          }
        }
      } catch (\Exception $e) {
        \Drupal::logger('hotlinks')->error('Failed to create test category @name: @error', [
          '@name' => $parent_name,
          '@error' => $e->getMessage(),
        ]);
      }
    }

    return $created_count;
  }

  /**
   * Generate test users.
   *
   * @return int
   *   Number of users created.
   */
  protected function generateTestUsers() {
    $created_count = 0;

    foreach ($this->testUsers as $username) {
      try {
        // Check if user already exists
        $existing = $this->entityTypeManager
          ->getStorage('user')
          ->loadByProperties(['name' => $username]);

        if (empty($existing)) {
          $user = User::create([
            'name' => $username,
            'mail' => $username . '@example.com',
            'status' => 1,
            'pass' => $this->random->string(12),
            'roles' => ['authenticated'],
          ]);
          $user->save();
          $created_count++;
        }
      } catch (\Exception $e) {
        \Drupal::logger('hotlinks')->error('Failed to create test user @name: @error', [
          '@name' => $username,
          '@error' => $e->getMessage(),
        ]);
      }
    }

    return $created_count;
  }

  /**
   * Generate test hotlinks using ANY available categories.
   *
   * @param bool $generate_thumbnails
   *   Whether to generate placeholder thumbnails.
   *
   * @return int
   *   Number of hotlinks created.
   */
  protected function generateTestHotlinks($generate_thumbnails = TRUE) {
    $created_count = 0;

    foreach ($this->testHotlinks as $hotlink_data) {
      try {
        // Check if hotlink already exists
        $existing = $this->entityTypeManager
          ->getStorage('node')
          ->loadByProperties([
            'type' => 'hotlink',
            'title' => $hotlink_data['title'],
          ]);

        if (empty($existing)) {
          // Find category term IDs using smart matching
          $category_ids = $this->findCategoryIds($hotlink_data['categories']);

          // Skip if no categories found
          if (empty($category_ids)) {
            \Drupal::logger('hotlinks')->warning('No matching categories found for hotlink @title. Available categories: @categories', [
              '@title' => $hotlink_data['title'],
              '@categories' => implode(', ', $hotlink_data['categories']),
            ]);
            continue;
          }

          // Create hotlink node
          $node = Node::create([
            'type' => 'hotlink',
            'title' => $hotlink_data['title'],
            'field_hotlink_url' => [
              'uri' => $hotlink_data['url'],
              'title' => $hotlink_data['title'],
            ],
            'field_hotlink_description' => [
              'value' => $hotlink_data['description'],
              'format' => 'basic_html',
            ],
            'field_hotlink_category' => $category_ids,
            'status' => 1,
            'uid' => 1,
          ]);

          // Add placeholder thumbnail if requested
          if ($generate_thumbnails) {
            $thumbnail = $this->generatePlaceholderThumbnail($hotlink_data['title']);
            if ($thumbnail) {
              $node->set('field_hotlink_thumbnail', [
                'target_id' => $thumbnail->id(),
                'alt' => 'Thumbnail for ' . $hotlink_data['title'],
              ]);
            }
          }

          $node->save();
          $created_count++;

          \Drupal::logger('hotlinks')->info('Created hotlink @title with categories: @categories', [
            '@title' => $hotlink_data['title'],
            '@categories' => implode(', ', $this->getCategoryNamesFromIds($category_ids)),
          ]);
        }
      } catch (\Exception $e) {
        \Drupal::logger('hotlinks')->error('Failed to create test hotlink @title: @error', [
          '@title' => $hotlink_data['title'],
          '@error' => $e->getMessage(),
        ]);
      }
    }

    return $created_count;
  }

  /**
   * Find category IDs by matching category names intelligently.
   *
   * This method looks for exact matches first, then tries partial matches,
   * and works with ANY existing categories (not just test categories).
   *
   * @param array $category_names
   *   Array of category names to find.
   *
   * @return array
   *   Array of term IDs that were found.
   */
  protected function findCategoryIds(array $category_names) {
    $category_ids = [];
    
    // Get ALL existing categories
    $all_terms = $this->entityTypeManager
      ->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'hotlink_categories']);

    if (empty($all_terms)) {
      return [];
    }

    // Create a lookup array for faster searching
    $term_lookup = [];
    foreach ($all_terms as $term) {
      $name = $term->getName();
      $term_lookup[strtolower($name)] = $term->id();
      
      // Also add partial matches for common words
      $words = explode(' ', strtolower($name));
      foreach ($words as $word) {
        if (strlen($word) > 3) { // Only index longer words
          $term_lookup[$word] = $term->id();
        }
      }
    }

    foreach ($category_names as $category_name) {
      $found_id = NULL;
      $lower_name = strtolower($category_name);
      
      // Try exact match first
      if (isset($term_lookup[$lower_name])) {
        $found_id = $term_lookup[$lower_name];
      }
      // Try partial matches
      else {
        $words = explode(' ', $lower_name);
        foreach ($words as $word) {
          if (strlen($word) > 3 && isset($term_lookup[$word])) {
            $found_id = $term_lookup[$word];
            break;
          }
        }
        
        // Try fuzzy matching for common mappings
        $fuzzy_matches = [
          'ai & machine learning' => ['ai', 'machine', 'learning', 'artificial'],
          'movies & tv' => ['movies', 'tv', 'television', 'film'],
          'news & media' => ['news', 'media'],
          'health & wellness' => ['health', 'wellness', 'medical'],
          'space & astronomy' => ['space', 'astronomy', 'universe'],
        ];
        
        foreach ($fuzzy_matches as $pattern => $alternatives) {
          if (strpos($lower_name, $pattern) !== FALSE) {
            foreach ($alternatives as $alt) {
              if (isset($term_lookup[$alt])) {
                $found_id = $term_lookup[$alt];
                break 2;
              }
            }
          }
        }
      }
      
      if ($found_id && !in_array($found_id, $category_ids)) {
        $category_ids[] = $found_id;
      }
    }

    // If no specific categories found, use the first available category
    // This ensures hotlinks always get assigned to SOMETHING
    if (empty($category_ids) && !empty($all_terms)) {
      $first_term = reset($all_terms);
      $category_ids[] = $first_term->id();
      
      \Drupal::logger('hotlinks')->info('No specific categories found, using fallback category: @name', [
        '@name' => $first_term->getName(),
      ]);
    }

    return $category_ids;
  }

  /**
   * Get category names from IDs for logging.
   *
   * @param array $category_ids
   *   Array of term IDs.
   *
   * @return array
   *   Array of category names.
   */
  protected function getCategoryNamesFromIds(array $category_ids) {
    $names = [];
    $terms = $this->entityTypeManager
      ->getStorage('taxonomy_term')
      ->loadMultiple($category_ids);
    
    foreach ($terms as $term) {
      $names[] = $term->getName();
    }
    
    return $names;
  }

  /**
   * Generate test reviews and ratings.
   *
   * @return int
   *   Number of reviews created.
   */
  protected function generateTestReviews() {
    if (!\Drupal::moduleHandler()->moduleExists('hotlinks_reviews')) {
      return 0;
    }

    $created_count = 0;
    $reviews_service = \Drupal::service('hotlinks_reviews.service');

    // Get all hotlinks
    $hotlinks = $this->entityTypeManager
      ->getStorage('node')
      ->loadByProperties(['type' => 'hotlink']);

    // Get test users
    $test_users = $this->entityTypeManager
      ->getStorage('user')
      ->loadByProperties(['name' => $this->testUsers]);

    if (empty($hotlinks) || empty($test_users)) {
      return 0;
    }

    $user_ids = array_keys($test_users);

    foreach ($hotlinks as $hotlink) {
      try {
        // Generate 2-5 reviews per hotlink
        $review_count = rand(2, 5);
        $used_users = [];

        for ($i = 0; $i < $review_count; $i++) {
          // Pick a random user who hasn't reviewed this hotlink
          $available_users = array_diff($user_ids, $used_users);
          if (empty($available_users)) {
            break;
          }

          $user_id = $available_users[array_rand($available_users)];
          $used_users[] = $user_id;

          // Pick a random review
          $review_data = $this->sampleReviews[array_rand($this->sampleReviews)];

          // Submit rating
          $reviews_service->submitRating(
            $hotlink->id(),
            $review_data['rating'],
            '127.0.0.1',
            $user_id
          );

          // Submit review (50% chance)
          if (rand(0, 1)) {
            $reviews_service->submitReview(
              $hotlink->id(),
              $review_data['text'],
              '127.0.0.1',
              $review_data['rating'],
              NULL,
              $user_id
            );
          }

          $created_count++;
        }

        // Update statistics
        $reviews_service->updateNodeStatistics($hotlink->id());

      } catch (\Exception $e) {
        \Drupal::logger('hotlinks')->error('Failed to create reviews for @title: @error', [
          '@title' => $hotlink->getTitle(),
          '@error' => $e->getMessage(),
        ]);
      }
    }

    return $created_count;
  }

  /**
   * Generate a placeholder thumbnail image.
   *
   * @param string $title
   *   The title for the placeholder.
   *
   * @return \Drupal\file\Entity\File|null
   *   The created file entity or NULL on failure.
   */
  protected function generatePlaceholderThumbnail($title) {
    try {
      // Create a simple placeholder image using GD
      $width = 300;
      $height = 200;

      if (!extension_loaded('gd')) {
        return NULL;
      }

      $image = imagecreate($width, $height);
      
      // Colors
      $bg_color = imagecolorallocate($image, 240, 240, 240);
      $text_color = imagecolorallocate($image, 100, 100, 100);
      $border_color = imagecolorallocate($image, 200, 200, 200);

      // Fill background
      imagefill($image, 0, 0, $bg_color);

      // Draw border
      imagerectangle($image, 0, 0, $width - 1, $height - 1, $border_color);

      // Add text (first 20 characters of title)
      $text = substr($title, 0, 20);
      if (strlen($title) > 20) {
        $text .= '...';
      }

      // Calculate text position (center)
      $font_size = 12;
      $text_width = strlen($text) * 7; // Approximate width
      $text_x = ($width - $text_width) / 2;
      $text_y = $height / 2;

      imagestring($image, 3, $text_x, $text_y - 10, $text, $text_color);
      imagestring($image, 2, $text_x, $text_y + 10, 'Placeholder', $text_color);

      // Save to temporary file
      $temp_file = tempnam(sys_get_temp_dir(), 'hotlinks_thumb_');
      imagepng($image, $temp_file);
      imagedestroy($image);

      // Read file content
      $image_data = file_get_contents($temp_file);
      unlink($temp_file);

      // Create filename
      $filename = 'placeholder_' . preg_replace('/[^a-zA-Z0-9]/', '_', strtolower($title)) . '_' . time() . '.png';

      // Ensure directory exists
      $directory = 'public://hotlinks/thumbnails';
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

      // Save file
      $file_uri = $directory . '/' . $filename;
      $saved_file = $this->fileSystem->saveData($image_data, $file_uri, FileSystemInterface::EXISTS_REPLACE);

      if ($saved_file) {
        // Create file entity
        $file_entity = File::create([
          'uri' => $saved_file,
          'status' => 1,
          'uid' => 1,
        ]);
        $file_entity->save();

        return $file_entity;
      }

    } catch (\Exception $e) {
      \Drupal::logger('hotlinks')->error('Failed to create placeholder thumbnail: @error', [
        '@error' => $e->getMessage(),
      ]);
    }

    return NULL;
  }

  /**
   * Remove all test data.
   *
   * @return array
   *   Results of the cleanup process.
   */
  public function removeTestData() {
    $results = [
      'hotlinks' => 0,
      'categories' => 0,
      'users' => 0,
      'reviews' => 0,
      'errors' => [],
    ];

    try {
      // Remove test hotlinks
      $hotlinks = $this->entityTypeManager
        ->getStorage('node')
        ->loadByProperties(['type' => 'hotlink']);

      foreach ($hotlinks as $hotlink) {
        foreach ($this->testHotlinks as $test_data) {
          if ($hotlink->getTitle() === $test_data['title']) {
            $hotlink->delete();
            $results['hotlinks']++;
            break;
          }
        }
      }

      // Remove test users
      foreach ($this->testUsers as $username) {
        $users = $this->entityTypeManager
          ->getStorage('user')
          ->loadByProperties(['name' => $username]);
        
        foreach ($users as $user) {
          $user->delete();
          $results['users']++;
        }
      }

      // Remove test categories (only the specific test ones)
      foreach (array_keys($this->testCategories) as $category_name) {
        $terms = $this->entityTypeManager
          ->getStorage('taxonomy_term')
          ->loadByProperties([
            'vid' => 'hotlink_categories',
            'name' => $category_name,
          ]);
        
        foreach ($terms as $term) {
          // Delete children first
          $children = $this->entityTypeManager
            ->getStorage('taxonomy_term')
            ->loadChildren($term->id());
          
          foreach ($children as $child) {
            // Only delete if it's one of our test subcategories
            if (in_array($child->getName(), $this->getTestSubcategoryNames())) {
              $child->delete();
              $results['categories']++;
            }
          }
          
          $term->delete();
          $results['categories']++;
        }
      }

      // Clean up reviews database (if module exists)
      if (\Drupal::moduleHandler()->moduleExists('hotlinks_reviews')) {
        $deleted = $this->database->delete('hotlinks_ratings')->execute();
        $results['reviews'] += $deleted;
        
        $deleted = $this->database->delete('hotlinks_reviews')->execute();
        $results['reviews'] += $deleted;
        
        $this->database->delete('hotlinks_statistics')->execute();
        $this->database->delete('hotlinks_rate_limits')->execute();
      }

    } catch (\Exception $e) {
      $results['errors'][] = $e->getMessage();
      \Drupal::logger('hotlinks')->error('Test data removal failed: @error', [
        '@error' => $e->getMessage(),
      ]);
    }

    return $results;
  }

  /**
   * Get all test subcategory names for cleanup purposes.
   *
   * @return array
   *   Array of all test subcategory names.
   */
  protected function getTestSubcategoryNames() {
    $subcategories = [];
    foreach ($this->testCategories as $category_data) {
      $subcategories = array_merge($subcategories, $category_data['subcategories']);
    }
    return $subcategories;
  }

  /**
   * Get information about what categories are available.
   *
   * @return array
   *   Information about available categories.
   */
  public function getCategoryInfo() {
    $all_terms = $this->entityTypeManager
      ->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'hotlink_categories']);

    $info = [
      'total_categories' => count($all_terms),
      'category_names' => [],
      'has_test_categories' => FALSE,
    ];

    foreach ($all_terms as $term) {
      $name = $term->getName();
      $info['category_names'][] = $name;
      
      if (array_key_exists($name, $this->testCategories)) {
        $info['has_test_categories'] = TRUE;
      }
    }

    return $info;
  }

  /**
   * Check if specific test categories exist.
   *
   * @return bool
   *   TRUE if test categories exist.
   */
  public function testCategoriesExist() {
    $test_category_names = array_keys($this->testCategories);
    
    $existing = $this->entityTypeManager
      ->getStorage('taxonomy_term')
      ->loadByProperties([
        'vid' => 'hotlink_categories',
        'name' => $test_category_names,
      ]);

    return !empty($existing);
  }

  /**
   * Generate hotlinks using existing categories regardless of their names.
   *
   * This method will create test hotlinks and assign them to the best
   * matching existing categories, even if they're not the standard test categories.
   *
   * @param bool $generate_thumbnails
   *   Whether to generate thumbnails.
   *
   * @return int
   *   Number of hotlinks created.
   */
  public function generateHotlinksForExistingCategories($generate_thumbnails = TRUE) {
    // Get all existing categories
    $all_terms = $this->entityTypeManager
      ->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'hotlink_categories']);

    if (empty($all_terms)) {
      throw new \Exception('No categories exist. Please create some categories first.');
    }

    $created_count = 0;

    // Create a simplified version of test hotlinks that can work with any categories
    $generic_hotlinks = [
      [
        'title' => 'GitHub - Where Software is Built',
        'url' => 'https://github.com',
        'description' => 'The world\'s leading platform for version control and collaboration.',
        'keywords' => ['technology', 'programming', 'development', 'code', 'software'],
      ],
      [
        'title' => 'Stack Overflow - Developer Community',
        'url' => 'https://stackoverflow.com',
        'description' => 'The largest online community for programmers to learn and share knowledge.',
        'keywords' => ['technology', 'programming', 'development', 'help', 'community'],
      ],
      [
        'title' => 'NASA - Space Exploration',
        'url' => 'https://nasa.gov',
        'description' => 'Official website of NASA with space exploration news and missions.',
        'keywords' => ['science', 'space', 'astronomy', 'research', 'exploration'],
      ],
      [
        'title' => 'BBC News',
        'url' => 'https://bbc.com/news',
        'description' => 'Breaking news and world news from the BBC.',
        'keywords' => ['news', 'media', 'world', 'current', 'events'],
      ],
      [
        'title' => 'Coursera - Online Learning',
        'url' => 'https://coursera.org',
        'description' => 'Access courses from top universities worldwide.',
        'keywords' => ['education', 'learning', 'courses', 'university', 'online'],
      ],
      [
        'title' => 'IMDb - Movie Database',
        'url' => 'https://imdb.com',
        'description' => 'The world\'s most popular movie database.',
        'keywords' => ['entertainment', 'movies', 'tv', 'film', 'media'],
      ],
      [
        'title' => 'WebMD - Health Information',
        'url' => 'https://webmd.com',
        'description' => 'Medical information and health advice you can trust.',
        'keywords' => ['health', 'medical', 'wellness', 'medicine', 'care'],
      ],
    ];

    foreach ($generic_hotlinks as $hotlink_data) {
      try {
        // Check if already exists
        $existing = $this->entityTypeManager
          ->getStorage('node')
          ->loadByProperties([
            'type' => 'hotlink',
            'title' => $hotlink_data['title'],
          ]);

        if (!empty($existing)) {
          continue;
        }

        // Find best matching categories
        $category_ids = $this->findBestMatchingCategories($hotlink_data['keywords'], $all_terms);

        // Create the hotlink
        $node = Node::create([
          'type' => 'hotlink',
          'title' => $hotlink_data['title'],
          'field_hotlink_url' => [
            'uri' => $hotlink_data['url'],
            'title' => $hotlink_data['title'],
          ],
          'field_hotlink_description' => [
            'value' => $hotlink_data['description'],
            'format' => 'basic_html',
          ],
          'field_hotlink_category' => $category_ids,
          'status' => 1,
          'uid' => 1,
        ]);

        if ($generate_thumbnails) {
          $thumbnail = $this->generatePlaceholderThumbnail($hotlink_data['title']);
          if ($thumbnail) {
            $node->set('field_hotlink_thumbnail', [
              'target_id' => $thumbnail->id(),
              'alt' => 'Thumbnail for ' . $hotlink_data['title'],
            ]);
          }
        }

        $node->save();
        $created_count++;

        \Drupal::logger('hotlinks')->info('Created hotlink @title in existing categories', [
          '@title' => $hotlink_data['title'],
        ]);

      } catch (\Exception $e) {
        \Drupal::logger('hotlinks')->error('Failed to create hotlink @title: @error', [
          '@title' => $hotlink_data['title'],
          '@error' => $e->getMessage(),
        ]);
      }
    }

    return $created_count;
  }

  /**
   * Find best matching categories based on keywords.
   *
   * @param array $keywords
   *   Keywords to match against.
   * @param array $available_terms
   *   Available taxonomy terms.
   *
   * @return array
   *   Array of term IDs.
   */
  protected function findBestMatchingCategories(array $keywords, array $available_terms) {
    $matches = [];
    $scores = [];

    foreach ($available_terms as $term) {
      $term_name = strtolower($term->getName());
      $score = 0;

      foreach ($keywords as $keyword) {
        $keyword = strtolower($keyword);
        
        // Exact match gets high score
        if ($term_name === $keyword) {
          $score += 10;
        }
        // Partial match gets medium score
        elseif (strpos($term_name, $keyword) !== FALSE) {
          $score += 5;
        }
        // Word match gets low score
        elseif (strpos($keyword, $term_name) !== FALSE) {
          $score += 3;
        }
      }

      if ($score > 0) {
        $scores[$term->id()] = $score;
      }
    }

    // Sort by score and take top matches
    arsort($scores);
    $top_matches = array_slice(array_keys($scores), 0, 3, TRUE);

    // If no matches, use first available category
    if (empty($top_matches)) {
      $first_term = reset($available_terms);
      $top_matches = [$first_term->id()];
    }

    return $top_matches;
  }
}